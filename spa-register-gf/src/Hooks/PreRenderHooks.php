<?php
namespace SpaRegisterGf\Hooks;

use SpaRegisterGf\Infrastructure\GFFormFinder;
use SpaRegisterGf\Infrastructure\Logger;
use SpaRegisterGf\Services\SessionService;
use SpaRegisterGf\Services\FieldMapService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hook: gform_pre_render
 */
class PreRenderHooks {

    private const FREQUENCY_LABELS = [
        'spa_price_1x_weekly' => '1× týždenne',
        'spa_price_2x_weekly' => '2× týždenne',
        'spa_price_monthly'   => 'Mesačný paušál',
        'spa_price_semester'  => 'Cena za semester',
    ];

    private const FREQUENCY_PERIOD = [
        'spa_price_1x_weekly' => 'za tréningový týždeň',
        'spa_price_2x_weekly' => 'za tréningový týždeň',
        'spa_price_monthly'   => 'za kalendárny mesiac',
        'spa_price_semester'  => 'za semester',
    ];

    private const DAY_LABELS = [
        'monday'    => 'Pondelok',
        'tuesday'   => 'Utorok',
        'wednesday' => 'Streda',
        'thursday'  => 'Štvrtok',
        'friday'    => 'Piatok',
        'saturday'  => 'Sobota',
        'sunday'    => 'Nedeľa',
    ];

    // ────────────────────────────────────────────────────────────────────────

    public function handle( array $form ): array {
        if ( ! GFFormFinder::guard( $form ) ) {
            return $form;
        }

        $session = SessionService::tryCreate();

        $cityFieldId    = FieldMapService::tryResolve( 'spa_city' );
        $programFieldId = FieldMapService::tryResolve( 'spa_program' );

        if ( $session ) {
            if ( $programFieldId && $session->getProgramId() > 0 ) {
                add_filter( 'gform_field_value_' . $programFieldId, function () use ( $session ) {
                    return $session->getProgramId();
                } );
            }

            // spa_resolved_type predvyplníme zo session.scope
            $resolvedTypeFieldId = FieldMapService::tryResolve( 'spa_resolved_type' );
            try {
                $scope = $session->getScope();

                if ( $resolvedTypeFieldId ) {
                    add_filter( 'gform_field_value_' . $resolvedTypeFieldId, function () use ( $scope ) {
                        return $scope;
                    } );
                }

                wp_add_inline_script(
                    'spa-register-gf-js',
                    'window.spaRegisterScope = "' . esc_js( $scope ) . '";',
                    'before'
                );

                $logicalKeysToOverride = [];

                if ( $scope === 'adult' ) {
                    $logicalKeysToOverride = [
                        'spa_guardian_name',
                        'spa_parent_email',
                        'spa_parent_phone',
                        'spa_consent_guardian',
                    ];
                } elseif ( $scope === 'child' ) {
                    $logicalKeysToOverride = [
                        'spa_client_email_required',
                    ];
                }

                if ( ! empty( $logicalKeysToOverride ) ) {
                    $fieldIds         = [];
                    $overriddenFields = [];

                    foreach ( $logicalKeysToOverride as $logicalKey ) {
                        $resolvedId = FieldMapService::tryResolve( $logicalKey );
                        if ( $resolvedId ) {
                            $fieldIds[ $logicalKey ] = (int) $resolvedId;
                        }
                    }

                    if ( ! empty( $fieldIds ) ) {
                        foreach ( $form['fields'] as &$field ) {
                            $fieldId = isset( $field->id ) ? (int) $field->id : 0;

                            if ( $fieldId <= 0 ) {
                                continue;
                            }

                            foreach ( $fieldIds as $logicalKey => $resolvedId ) {
                                if ( $fieldId === (int) $resolvedId ) {
                                    $field->isRequired = false;

                                    if ( ! in_array( $logicalKey, $overriddenFields, true ) ) {
                                        $overriddenFields[] = $logicalKey;
                                    }
                                }
                            }
                        }
                        unset( $field );
                    }

                    if ( ! empty( $overriddenFields ) ) {
                        Logger::info( 'scope_required_override', [
                            'scope'             => $scope,
                            'overridden_fields' => $overriddenFields,
                        ] );
                    }
                }

            } catch ( \RuntimeException $e ) {
                // scope chýba – formulár sa zobrazí bez predvyplnenia
            }

            $freqFieldId = FieldMapService::tryResolve( 'spa_frequency' );
            if ( $freqFieldId && ! empty( $session->getFrequencyKey() ) ) {
                add_filter( 'gform_field_value_' . $freqFieldId, function () use ( $session ) {
                    return $session->getFrequencyKey();
                } );
            }

            $summaryResult  = $this->buildPriceSummary( $session );
            $summaryHtml    = $summaryResult['html'];
            $finalAmount    = $summaryResult['finalAmount'];

            $targetInput = FieldMapService::tryResolve( 'spa_first_payment_amount' );
            $targetId    = $targetInput ? (int) str_replace( 'input_', '', $targetInput ) : 0;

            foreach ( $form['fields'] as &$field ) {
                if (
                    $field->type === 'html' &&
                    strpos( $field->cssClass ?? '', 'info_price_summary' ) !== false
                ) {
                    $field->content = $summaryHtml !== ''
                        ? $summaryHtml
                        : $this->buildSummaryFallback();
                }
                if ( $targetId > 0 && (int) $field->id === $targetId ) {
                    $field->defaultValue = $finalAmount;
                    $field->value        = $finalAmount;
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( '[spa-register-gf] hidden set via mapping id=' . $targetId . ' value=' . $finalAmount );
                    }
                }
            }
        }

            // Fallback ak session úplne chýba – skry všetky polia, zobraz chybovú správu
        if ( ! $session ) {
            foreach ( $form['fields'] as &$field ) {
                if (
                    $field->type === 'html' &&
                    strpos( $field->cssClass ?? '', 'info_price_summary' ) !== false
                ) {
                    $field->content = $this->buildSummaryFallback();
                    break;
                }
            }

            wp_add_inline_script(
                'spa-register-gf-js',
                '(function(){
                    function spaMaskForm(){
                        var form = document.querySelector("form.spa-register-gf");
                        if(!form) return;
                        var fields = form.querySelectorAll(".gfield:not(.gfield--type-html)");
                        fields.forEach(function(f){ f.style.display="none"; });
                        var buttons = form.querySelectorAll(".gform_footer, .gform_page_footer");
                        buttons.forEach(function(b){ b.style.display="none"; });
                    }
                    if(document.readyState === "loading"){
                        document.addEventListener("DOMContentLoaded", spaMaskForm);
                    } else {
                        spaMaskForm();
                    }
                })();',
                'after'
            );
        }

        if ( $cityFieldId && isset( $_GET['city'] ) ) {
            $cityVal = sanitize_text_field( $_GET['city'] );
            add_filter( 'gform_field_value_' . $cityFieldId, function () use ( $cityVal ) {
                return $cityVal;
            } );
        }

        if ( $programFieldId && isset( $_GET['program'] ) && ( ! $session || $session->getProgramId() <= 0 ) ) {
            $programVal = intval( $_GET['program'] );
            add_filter( 'gform_field_value_' . $programFieldId, function () use ( $programVal ) {
                return $programVal;
            } );
        }

        // Scope-based isRequired overrides for parent fields/sections.
        $form = $this->applyScopeRequiredOverrides( $form );
        // Company_* required overrides podľa spôsobu platby a voľby "fakturovať na firmu".
        $form = $this->applyCompanyRequiredOverrides( $form );

        return $form;
    }

    /**
     * gform_pre_validation – scope-based isRequired overrides.
     *
     * This runs in addition to ValidationHooks::handlePreValidation().
     */
    public function handlePreValidationScope( array $form ): array {
        if ( ! GFFormFinder::guard( $form ) ) {
            return $form;
        }

        // Najskôr zachovaj existujúce scope-based overrides (child/adult)
        $form = $this->applyScopeRequiredOverrides( $form );
        // Company_* required overrides podľa spôsobu platby a voľby "fakturovať na firmu".
        $form = $this->applyCompanyRequiredOverrides( $form );

        return $form;
    }

    /**
     * gform_pre_submission_filter – scope-based isRequired overrides.
     *
     * Signature keeps only $form; GF passes $entry as second param which is ignored.
     */
    public function handlePreSubmissionScope( array $form ): array {
        if ( ! GFFormFinder::guard( $form ) ) {
            return $form;
        }

        // Najskôr zachovaj existujúce scope-based overrides (child/adult)
        $form = $this->applyScopeRequiredOverrides( $form );
        // Company_* required overrides podľa spôsobu platby a voľby "fakturovať na firmu".
        $form = $this->applyCompanyRequiredOverrides( $form );

        return $form;
    }

    /**
     * Dynamicky upraví isRequired pre "parent" polia podľa session scope.
     *
     * Scope čítame priamo zo $_SESSION['spa_registration']['scope'].
     * - Ak scope nie je 'child' ani 'adult', formulár nemeníme.
     * - Parent pole/sekcia je rozpoznaná podľa CSS tried:
     *   - spa-section-parent
     *   - spa-parent-field
     */
    private function applyScopeRequiredOverrides( array $form ): array {

        $scope = $_SESSION['spa_registration']['scope'] ?? null;

        if ( $scope !== 'adult' ) {
            return $form;
        }

        if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
            return $form;
        }

        foreach ( $form['fields'] as &$field ) {
    
            $css = $field->cssClass ?? '';

            if ( ! is_string( $css ) || $css === '' ) {
                continue;
            }

            $isParentField =
                strpos( $css, 'spa-parent' ) !== false
                || strpos( $css, 'spa-guardian' ) !== false
                || strpos( $css, 'company_' ) !== false;
    
            if ( $isParentField ) {
    
                // LEN neutralizácia pre adult
                $field->isRequired         = false;
                $field->failed_validation  = false;
                $field->validation_message = '';
            }
        }
    
        unset( $field );
    
        return $form;
    }

    /**
     * Fakturačné polia (company_*) – required override podľa platby.
     *
     * company_* sú required iba ak:
     * - payment_method === 'invoice_payment'
     * - a checkbox spa_invoice_tocompany je truthy
     *
     * Inak:
     * - isRequired = false
     * - failed_validation = false
     * - validation_message = ''
     *
     * Identifikácia company_* výhradne podľa cssClass obsahujúcej "company_".
     * Hodnoty čítame z $_POST cez FieldMapService (bez natvrdo ID).
     */
    private function applyCompanyRequiredOverrides( array $form ): array {
        $paymentFieldKey     = 'payment_method';
        $invoiceToCompanyKey = 'spa_invoice_tocompany';

        $paymentFieldId     = FieldMapService::tryResolve( $paymentFieldKey );
        $invoiceToCompanyId = FieldMapService::tryResolve( $invoiceToCompanyKey );

        // GF POST keys používajú podčiarkovník namiesto bodky (input_48.1 → input_48_1)
        $paymentPostKey = $paymentFieldId ? str_replace( '.', '_', $paymentFieldId ) : null;
        $invoicePostKey = $invoiceToCompanyId ? str_replace( '.', '_', $invoiceToCompanyId ) : null;

        $paymentValue        = $paymentPostKey ? \rgpost( $paymentPostKey ) : null;
        $invoiceToCompanyRaw = $invoicePostKey ? \rgpost( $invoicePostKey ) : null;

        // Robustné vyhodnotenie checkboxu "Faktúrovať na firmu?"
        // - Ak GF vráti array → hľadáme value 'invoice_tocompany'
        // - Ak vráti string → porovnávame na 'invoice_tocompany' (alebo obsahuje)
        $invoiceChecked = false;
        if ( is_array( $invoiceToCompanyRaw ) ) {
            $invoiceChecked = in_array( 'invoice_tocompany', $invoiceToCompanyRaw, true );
            if ( ! $invoiceChecked ) {
                // fallback: akékoľvek neprázdne hodnoty v poli
                $nonEmpty = array_filter(
                    $invoiceToCompanyRaw,
                    static function ( $v ) {
                        return $v !== null && $v !== '';
                    }
                );
                $invoiceChecked = ! empty( $nonEmpty );
            }
        } elseif ( is_string( $invoiceToCompanyRaw ) && $invoiceToCompanyRaw !== '' ) {
            $invoiceChecked = (
                $invoiceToCompanyRaw === 'invoice_tocompany'
                || str_contains( $invoiceToCompanyRaw, 'invoice_tocompany' )
            );
            if ( ! $invoiceChecked ) {
                // fallback: akýkoľvek neprázdny string považujeme za "zaškrtnuté"
                $invoiceChecked = true;
            }
        }

        $shouldRequireCompany = (
            $paymentValue === 'invoice_payment'
            && $invoiceChecked
        );

        // DEBUG toggle – dá sa vypnúť cez filter `spa_register_gf_debug_company`.
        $debugCompany = apply_filters(
            'spa_register_gf_debug_company',
            defined( 'WP_DEBUG' ) && WP_DEBUG
        );

        if ( $debugCompany ) {
            $scope     = $_SESSION['spa_registration']['scope'] ?? null;
            $sessionId = session_id();

            $debug = [
                'session_id'          => $sessionId ?: null,
                'scope'               => $scope,
                'payment_logical_key' => $paymentFieldKey,
                'payment_field_id'    => $paymentFieldId,
                'payment_post_key'    => $paymentPostKey,
                'payment_value_type'  => gettype( $paymentValue ),
                'payment_value'       => $paymentValue,
                'invoice_logical_key' => $invoiceToCompanyKey,
                'invoice_field_id'    => $invoiceToCompanyId,
                'invoice_post_key'    => $invoicePostKey,
                'invoice_value_type'  => gettype( $invoiceToCompanyRaw ),
                'invoice_value'       => $invoiceToCompanyRaw,
                'should_require'      => $shouldRequireCompany,
            ];

            if ( headers_sent( $file, $line ) ) {
                $debug['headers_sent'] = [
                    'file' => $file,
                    'line' => $line,
                ];
            }

            error_log( '[spa-register-gf] company_required_debug: ' . wp_json_encode( $debug ) );
        }

        if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
            return $form;
        }

        foreach ( $form['fields'] as &$field ) {
            $css = $field->cssClass ?? '';

            // company_* identifikujeme výhradne podľa CSS triedy (nie podľa ID)
            if ( ! is_string( $css ) || $css === '' || strpos( $css, 'company_' ) === false ) {
                continue;
            }

            $beforeRequired = isset( $field->isRequired ) ? (bool) $field->isRequired : false;

            if ( $shouldRequireCompany ) {
                // Pri fakturácii na firmu nastavíme company_* ako required
                $field->isRequired = true;
            } else {
                // V ostatných prípadoch company_* NIKDY nesmú byť required ani mať chybu
                $field->isRequired         = false;
                $field->failed_validation  = false;
                $field->validation_message = '';
            }

            if ( $debugCompany ) {
                $fieldId = isset( $field->id ) ? (string) $field->id : '';
                error_log(
                    '[spa-register-gf] company_field_required_toggle: '
                    . 'field_id=' . $fieldId
                    . ' cssClass=' . $css
                    . ' before=' . ( $beforeRequired ? '1' : '0' )
                    . ' after=' . ( $field->isRequired ? '1' : '0' )
                );
            }
        }
        unset( $field );

        return $form;
    }

    // ════════════════════════════════════════════════════════════════════════
    // PRICE SUMMARY BUILDER
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @return array{html: string, finalAmount: float}
     */
    private function buildPriceSummary( SessionService $session ): array {
        $programId    = $session->getProgramId();
        $frequencyKey = $session->getFrequencyKey();
        $baseAmount   = method_exists( $session, 'getAmount' )            ? (float)  $session->getAmount()            : 0.0;
        $surchargeRaw = method_exists( $session, 'getExternalSurcharge' ) ? (string) $session->getExternalSurcharge() : '';

        try {
            $scope = $session->getScope();
        } catch ( \RuntimeException $e ) {
            $scope = '';
        }

        if ( $programId <= 0 ) {
            return [ 'html' => '', 'finalAmount' => 0.0 ];
        }

        $post = get_post( $programId );
        if ( ! $post || $post->post_status !== 'publish' ) {
            return [ 'html' => '', 'finalAmount' => 0.0 ];
        }

        $programName = esc_html( $post->post_title );

        // ── Vek ──────────────────────────────────────────────────────────────
        $ageMin = ( get_post_meta( $programId, 'spa_age_from', true ) !== '' )
            ? (float) get_post_meta( $programId, 'spa_age_from', true ) : null;
        $ageMax = ( get_post_meta( $programId, 'spa_age_to', true ) !== '' )
            ? (float) get_post_meta( $programId, 'spa_age_to', true ) : null;

        // ── Miesto – kompletná adresa: "Košice • Drábova 3 (ZŠ Drábova)" ────
        // Rovnaká logika ako renderFullInfobox() v spa-selection.js
        $locationText = '';
        $placeId = get_post_meta( $programId, 'spa_place_id', true );
        if ( $placeId ) {
            $placePost = get_post( (int) $placeId );
            if ( $placePost && $placePost->post_status === 'publish' ) {
                $placeCity    = get_post_meta( (int) $placeId, 'spa_place_city',    true );
                $placeAddress = get_post_meta( (int) $placeId, 'spa_place_address', true );
                $placeName    = $placePost->post_title;

                // Formát: "Košice • Drábova 3 (ZŠ Drábova)"
                $parts = [];
                if ( $placeCity )    $parts[] = '<strong>' . esc_html( $placeCity ) . '</strong>';
                if ( $placeAddress ) $parts[] = esc_html( $placeAddress );
                if ( $placeName )    $parts[] = '(<i>' . esc_html( $placeName ) . '</i>)';

                $locationText = implode( ' • ', array_filter( [
                    $placeCity ? '<strong>' . esc_html( $placeCity ) . '</strong>' : '',
                    $placeAddress && $placeName
                        ? esc_html( $placeAddress ) . ' (<i>' . esc_html( $placeName ) . '</i>)'
                        : esc_html( $placeAddress . $placeName ),
                ] ) );
            }
        }

        // ── Rozvrh ───────────────────────────────────────────────────────────
        $scheduleText = $this->buildScheduleText( $programId );

        // ── Cena z DB ────────────────────────────────────────────────────────
        $dbAmount = 0.0;
        if ( $frequencyKey && array_key_exists( $frequencyKey, self::FREQUENCY_LABELS ) ) {
            $metaVal  = get_post_meta( $programId, $frequencyKey, true );
            $dbAmount = ( $metaVal !== '' && $metaVal !== null ) ? (float) $metaVal : $baseAmount;
        } else {
            $dbAmount = $baseAmount;
        }

        // ── Surcharge ────────────────────────────────────────────────────────
        $finalAmount    = $dbAmount;
        $surchargeLabel = '';
        $hasSurcharge   = false;

        if ( $surchargeRaw !== '' && $surchargeRaw !== '0' ) {
            $isPercent = str_ends_with( $surchargeRaw, '%' );
            $numVal    = (float) str_replace( '%', '', $surchargeRaw );
            if ( $numVal !== 0.0 ) {
                $hasSurcharge = true;
                if ( $isPercent ) {
                    $finalAmount    = $dbAmount * ( 1 + $numVal / 100 );
                    $absVal         = abs( $numVal );
                    $surchargeLabel = $numVal > 0
                        ? 'Registračný príplatok +' . $absVal . '%'
                        : 'Registračná zľava -' . $absVal . '%';
                } else {
                    $finalAmount    = $dbAmount + $numVal;
                    $absVal         = abs( $numVal );
                    $surchargeLabel = $numVal > 0
                        ? 'Registračný príplatok +' . number_format( $absVal, 2, ',', ' ' ) . ' €'
                        : 'Registračná zľava -' . number_format( $absVal, 2, ',', ' ' ) . ' €';
                }
            }
        }
        $finalAmount = round( $finalAmount * 10 ) / 10;

        // ── Scope label ──────────────────────────────────────────────────────
        $scopeLabel = match ( $scope ) {
            'child' => 'Vybraný program je pre deti a vyžaduje údaje o zákonnom zástupcovi dieťaťa.',
            'adult' => 'Vyplňte vaše údaje pre registráciu pre váš vybraný program.',
            default => '',
        };

        // ── Vekový text ──────────────────────────────────────────────────────
        $ageText = '';
        if ( $ageMin !== null ) {
            $ageText  = str_replace( '.', ',', (string) $ageMin );
            $ageText .= $ageMax !== null ? '–' . str_replace( '.', ',', (string) $ageMax ) : '+';
            $upper    = $ageMax ?? $ageMin;
            $base     = (int) floor( $upper );
            $ageUnit  = match ( true ) {
                $base === 1              => 'rok',
                $base >= 2 && $base <= 4 => 'roky',
                default                  => 'rokov',
            };
            $ageText .= ' ' . $ageUnit;
        }

        $freqLabel   = self::FREQUENCY_LABELS[ $frequencyKey ]  ?? esc_html( $frequencyKey );
        $periodLabel = self::FREQUENCY_PERIOD[ $frequencyKey ]  ?? '';

        // ── SVG ikony – rovnaký helper a rovnaké volania ako spa-selection.php ─
        // spa_icon( $name, $css_class, $options[] )
        // options: 'fill', 'stroke'
        $strokeColor = 'var(--theme-palette-color-1)';
        $fillColor   = 'var(--theme-palette-color-1)';

        $iconLogo     = $this->icon( 'spa-icon',     'spa-logo-small',        [ 'stroke' => $strokeColor ] );
        $iconLocation = $this->icon( 'location',     'spa-icon-location',     [ 'stroke' => $strokeColor ] );
        $iconAge      = $this->icon( 'age',          'spa-icon-age',          [ 'stroke' => $strokeColor ] );
        $iconTime     = $this->icon( 'time',         'spa-icon-time',         [ 'stroke' => $strokeColor ] );
        $iconPriceW   = $this->icon( 'price-weekly', 'spa-icon-price-weekly', [ 'fill'   => $fillColor, 'stroke' => 'none' ] );
        $iconFreq     = $this->icon( 'frequency',    'spa-icon-frequency',    [ 'fill'   => $fillColor, 'stroke' => 'none' ] );
        $iconPrice    = $this->icon( 'price',        'spa-icon-price',        [ 'fill'   => $fillColor, 'stroke' => 'none' ] );

        // ════════════════════════════════════════════════════════════════════
        // HTML – štruktúra zhodná so .spa-infobox-summary v spa-selection.js
        // ════════════════════════════════════════════════════════════════════
        $html  = '<div class="spa-price-summary spa-infobox-summary">';
        $html .= '<ul class="spa-summary-list">';

        // 1. Program  
        $html .= '<li class="spa-summary-item spa-summary-program">';
        $html .= '<span class="spa-summary-icon">' . $iconLogo . '</span>';
        $html .= '<span><strong>' . $programName . '</strong>';
        $html .= '<span class="spa-summary-participant-name"></span>';
        $html .= '</span>';
        $html .= '</li>';

        // 2. Miesto – "Košice • Drábova 3 (ZŠ Drábova)"
        if ( $locationText ) {
            $html .= '<li class="spa-summary-item spa-summary-city">';
            $html .= '<span class="spa-summary-icon">' . $iconLocation . '</span>';
            $html .= $locationText; // obsahuje esc_html vnútri
            $html .= '</li>';
        }

        // 3. Vek
        if ( $ageText ) {
            $html .= '<li class="spa-summary-item spa-summary-age">';
            $html .= '<span class="spa-summary-icon">' . $iconAge . '</span>';
            $html .= '<strong>' . esc_html( $ageText ) . '</strong>';
            $html .= '</li>';            
        }

        // 4. Tréningové dni
        if ( $scheduleText ) {
            $html .= '<li class="spa-summary-item spa-summary-schedule">';
            $html .= '<span class="spa-summary-icon">' . $iconTime . '</span>';
            $html .= '<span><strong>Tréningové dni:</strong> ' . esc_html( $scheduleText ) . '</span>';
            $html .= '</li>';
        }

        // 5. Cena za frekvenciu
        if ( $dbAmount > 0 ) {
            $html .= '<li class="spa-summary-item spa-summary-price-weekly">';
            $html .= '<span class="spa-summary-icon">' . $iconPriceW . '</span>';
            $html .= '<strong>' . esc_html( $this->formatPrice( $dbAmount ) );
            $html .= '</strong>';
            if ( $periodLabel ) {
                $html .= ' ' . esc_html( $periodLabel );
            }            
            $html .= '</li>';

            $html .= '<li class="spa-summary-item spa-summary-frequency">';
            $html .= '<span class="spa-summary-icon">' . $iconFreq . '</span>';
            $html .= '<strong>Tréning</strong> ' . esc_html( $freqLabel );
            $html .= '</li>';
        }

        // 6. Cena k úhrade – zobrazí sa VŽDY (surcharge aj bez)
        if ( $hasSurcharge && $surchargeLabel && $finalAmount > 0) {
            $html .= '<li class="spa-summary-item spa-summary-price">';
            $html .= '<span class="spa-summary-icon">' . $iconPrice . '</span>';
            if ( $hasSurcharge && $surchargeLabel ) {
                $parts          = explode( ' ', $surchargeLabel );
                $surchargeValue = array_pop( $parts );
                $surchargeText  = implode( ' ', $parts );
                $html .= esc_html( $surchargeText ) . ' <strong>' . esc_html( $surchargeValue ) . '</strong>';
            } else {
            }
            $html .= '</li>';
        }
        // 7. Scope  
        if ( $scopeLabel ) {
            $html .= '<li class="spa-summary-item spa-scope-warning">';
            $html .= '<span>' . esc_html( $scopeLabel ) . '</span>';
            $html .= '</li>';
        }
        $html .= '</ul>';

        // Výška prvej úhrady – iba ak existuje external_surcharge
        if ( $finalAmount > 0 ) {
            $formattedFinal = $this->formatPrice( $finalAmount );
            // Oddeľ číslo od symbolu €: "46,50 €" → "46,50" + "€"
            $priceParts  = explode( ' ', $formattedFinal );
            $euroSymbol  = array_pop( $priceParts );
            $priceNumber = implode( ' ', $priceParts );
            $html .= '<div class="spa-summary-amount-final-price">';
            $html .= '<span>Výška prvej platby</span>';
            $html .= '<div class="final-price">' . esc_html( $priceNumber ) . '</span> <span class="final-price-symbol">' . esc_html( $euroSymbol ) . '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return [ 'html' => $html, 'finalAmount' => $finalAmount ];
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Wrapper pre ikony – použije témy helper spa_icon() ak existuje,
     * inak načíta SVG priamo z uploads/spa-icons/ (rovnaký adresár).
     * Signatúra spa_icon() z spa-selection.php:
     *   spa_icon( string $name, string $css_class, array $options )
     */
    private function icon( string $name, string $cssClass, array $options = [] ): string {
        if ( function_exists( 'spa_icon' ) ) {
            return (string) spa_icon( $name, $cssClass, $options );
        }

        // Fallback: priamy file_get_contents + inline style
        $path = WP_CONTENT_DIR . '/uploads/spa-icons/' . $name . '.svg';
        if ( ! file_exists( $path ) ) {
            return '';
        }

        $svg = (string) file_get_contents( $path );

        // Aplikuj fill/stroke ako inline style na <svg> tag
        $style = '';
        if ( ! empty( $options['fill'] ) )   $style .= 'fill:'   . $options['fill']   . ';';
        if ( ! empty( $options['stroke'] ) ) $style .= 'stroke:' . $options['stroke'] . ';';

        // Pridaj class
        $svg = preg_replace( '/<svg\b/i', '<svg class="' . esc_attr( $cssClass ) . '"', $svg, 1 );

        // Pridaj style
        if ( $style ) {
            if ( preg_match( '/style="[^"]*"/i', $svg ) ) {
                $svg = preg_replace( '/style="([^"]*)"/i', 'style="$1 ' . $style . '"', $svg, 1 );
            } else {
                $svg = preg_replace( '/<svg\b/i', '<svg style="' . $style . '"', $svg, 1 );
            }
        }

        return $svg;
    }

    /**
     * Tréningové dni z spa_schedule JSON.
     * Výstup: "Štvrtok 09:00–10:00, Piatok 16:00–17:00"
     */
    private function buildScheduleText( int $programId ): string {
        $json = get_post_meta( $programId, 'spa_schedule', true );
        if ( ! $json ) return '';

        $data = json_decode( $json, true );
        if ( ! is_array( $data ) || empty( $data ) ) return '';

        $scheduleMap = [];
        foreach ( $data as $item ) {
            $day = $item['day'] ?? '';
            if ( ! array_key_exists( $day, self::DAY_LABELS ) ) continue;
            $from = substr( $item['from'] ?? '', 0, 5 );
            $to   = ! empty( $item['to'] ) ? substr( $item['to'], 0, 5 ) : '';
            $scheduleMap[ $day ][] = $to ? $from . '–' . $to : $from;
        }

        if ( empty( $scheduleMap ) ) return '';

        $parts = [];
        foreach ( self::DAY_LABELS as $dayKey => $dayLabel ) {
            if ( ! isset( $scheduleMap[ $dayKey ] ) ) continue;
            $parts[] = $dayLabel . ' ' . implode( ', ', $scheduleMap[ $dayKey ] );
        }

        return implode( ', ', $parts );
    }

    /**
     * Fallback HTML ak session chýba alebo je neplatná.
     */
    private function buildSummaryFallback(): string {
        return '<div class="spa-alert-error">'
            . '<strong>Tréningový program nie je načítaný.</strong><br>'
            . 'Registrácia nemôže pokračovať bez výberu programu.<br>'
            . 'Prosím, vyberte program a pokračujte v registrácii!'
            . '</div>';
    }

    /**
     * Formátovanie ceny – zhodné s formatPriceForCard() v spa-selection-pricing.js
     */
    private function formatPrice( float $amount ): string {
        if ( $amount <= 0 ) return '0 €';
        return fmod( $amount, 1.0 ) === 0.0
            ? number_format( $amount, 0, ',', ' ' ) . ' €'
            : number_format( $amount, 2, ',', ' ' ) . ' €';
    }
}