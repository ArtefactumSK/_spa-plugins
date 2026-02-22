<?php
namespace SpaRegisterGf\Hooks;

use SpaRegisterGf\Infrastructure\GFFormFinder;
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

            } catch ( \RuntimeException $e ) {
                // scope chýba – formulár sa zobrazí bez predvyplnenia
            }

            // Vek validácia – zobrazí warning ak vek nezodpovedá rozsahu programu
            $ageMin = get_post_meta( $session->getProgramId(), 'spa_age_from', true );
            $ageMax = get_post_meta( $session->getProgramId(), 'spa_age_to',   true );

            if ( $ageMin !== '' || $ageMax !== '' ) {
                $ageMinJs = $ageMin !== '' ? (float) $ageMin : 'null';
                $ageMaxJs = $ageMax !== '' ? (float) $ageMax : 'null';

                wp_add_inline_script(
                    'spa-register-gf-js',
                    '(function(){
                        var ageMin = ' . $ageMinJs . ';
                        var ageMax = ' . $ageMaxJs . ';

                        function calcAge(val){
                            // Formát: dd.mm.rrrr
                            var parts = val.split(".");
                            if(parts.length !== 3) return null;
                            var d = parseInt(parts[0],10);
                            var m = parseInt(parts[1],10) - 1;
                            var y = parseInt(parts[2],10);
                            if(isNaN(d)||isNaN(m)||isNaN(y)||y < 1900) return null;
                            var today = new Date();
                            var birth = new Date(y, m, d);
                            if(birth > today) return null;
                            var age = today.getFullYear() - birth.getFullYear();
                            var mDiff = today.getMonth() - birth.getMonth();
                            if(mDiff < 0 || (mDiff === 0 && today.getDate() < birth.getDate())) age--;
                            return age;
                        }

                        function ageUnit(age){
                            if(age === 1) return "rok";
                            if(age >= 2 && age <= 4) return "roky";
                            return "rokov";
                        }

                        function checkAgeWarning(val){
                            var warning = document.querySelector(".spa-age-warning");
                            if(!warning) return;
                            var age = calcAge(val);
                            if(age === null){ warning.style.display = "none"; return; }
                            var outOfRange = false;
                            if(ageMin !== null && age < ageMin) outOfRange = true;
                            if(ageMax !== null && age > ageMax) outOfRange = true;
                            if(outOfRange){
                                warning.innerHTML = " Vek účastníka <span class=\"age-alert\">" + age + "</span> " + ageUnit(age) + " nezodpovedá vybranému programu!";
                                warning.style.display = "";
                            } else {
                                warning.style.display = "none";
                            }
                        } 



                        function bindBirthdate(){
                            // GF date field – hľadáme input s dd.mm.rrrr placeholder
                            var input = document.querySelector(".gfield input[placeholder=\"dd.mm.rrrr\"]");
                            if(!input) return;
                            input.addEventListener("change", function(){ checkAgeWarning(this.value); });
                            input.addEventListener("blur",   function(){ checkAgeWarning(this.value); });
                            // Ak je predvyplnené
                            if(input.value) checkAgeWarning(input.value);
                        }

                        if(document.readyState === "loading"){
                            document.addEventListener("DOMContentLoaded", bindBirthdate);
                        } else {
                            bindBirthdate();
                        }

                        // GF AJAX re-render (pagebreak späť/vpred)
                        document.addEventListener("gform_post_render", bindBirthdate);
                    })();',
                    'after'
                );
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
            if ( $ageMin !== null ) {
                $html .= '<span class="spa-age-warning"'
                    . ' data-age-min="' . esc_attr( (string) $ageMin ) . '"'
                    . ( $ageMax !== null ? ' data-age-max="' . esc_attr( (string) $ageMax ) . '"' : '' )
                    . ' style="display:none;">Vek účastníka nezodpovedá vybranému programu!</span>';
            }
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