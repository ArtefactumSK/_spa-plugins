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
 *
 * Predvyplnenie polí formulára podľa priority:
 *   SESSION > GET
 * GET je iba UI fallback pre spa_city a spa_program.
 * Plugin SESSION NESMIE meniť.
 */
class PreRenderHooks {

    // ── Frequency key → label (zhodné so spa-selection-pricing.js) ──────────
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

            $resolvedTypeFieldId = FieldMapService::tryResolve( 'spa_resolved_type' );
            if ( $resolvedTypeFieldId ) {
                try {
                    $scope = $session->getScope();
                    add_filter( 'gform_field_value_' . $resolvedTypeFieldId, function () use ( $scope ) {
                        return $scope;
                    } );
                } catch ( \RuntimeException $e ) {
                    // scope chýba
                }
            }

            $freqFieldId = FieldMapService::tryResolve( 'spa_frequency' );
            if ( $freqFieldId && ! empty( $session->getFrequencyKey() ) ) {
                add_filter( 'gform_field_value_' . $freqFieldId, function () use ( $session ) {
                    return $session->getFrequencyKey();
                } );
            }

            // ── Price summary inject ─────────────────────────────────────────
            $summaryHtml = $this->buildPriceSummary( $session );
            if ( $summaryHtml !== '' ) {
                foreach ( $form['fields'] as &$field ) {
                    if (
                        $field->type === 'html' &&
                        strpos( $field->cssClass ?? '', 'info_price_summary' ) !== false
                    ) {
                        $field->content = $summaryHtml;
                        break;
                    }
                }
                unset( $field );
            }
        }

        // GET fallback
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

    private function buildPriceSummary( SessionService $session ): string {
        $programId    = $session->getProgramId();
        $frequencyKey = $session->getFrequencyKey();
        $baseAmount   = method_exists( $session, 'getAmount' )             ? (float) $session->getAmount()             : 0.0;
        $surchargeRaw = method_exists( $session, 'getExternalSurcharge' )  ? (string) $session->getExternalSurcharge() : '';

        try {
            $scope = $session->getScope();
        } catch ( \RuntimeException $e ) {
            $scope = '';
        }

        if ( $programId <= 0 ) {
            return '';
        }

        // ── Program z CPT ────────────────────────────────────────────────────
        $post = get_post( $programId );
        if ( ! $post || $post->post_status !== 'publish' ) {
            return '';
        }

        $programName = esc_html( $post->post_title );

        // Vek z postmeta (spa_age_from / spa_age_to – rovnaké kľúče ako v spa-selection.php)
        $ageFrom = get_post_meta( $programId, 'spa_age_from', true );
        $ageTo   = get_post_meta( $programId, 'spa_age_to',   true );
        $ageMin  = ( $ageFrom !== '' && $ageFrom !== null ) ? (float) $ageFrom : null;
        $ageMax  = ( $ageTo   !== '' && $ageTo   !== null ) ? (float) $ageTo   : null;

        // Cena z DB (autoritatívny zdroj, nie session pre display)
        $dbAmount = 0.0;
        if ( $frequencyKey && isset( self::FREQUENCY_LABELS[ $frequencyKey ] ) ) {
            $metaVal  = get_post_meta( $programId, $frequencyKey, true );
            $dbAmount = ( $metaVal !== '' ) ? (float) $metaVal : $baseAmount;
        } else {
            $dbAmount = $baseAmount;
        }

        // ── Surcharge výpočet (logika zhodná so spa-selection-pricing.js) ───
        $finalAmount    = $dbAmount;
        $surchargeLabel = '';

        if ( $surchargeRaw !== '' && $surchargeRaw !== '0' && $surchargeRaw !== null ) {
            $isPercent = str_ends_with( $surchargeRaw, '%' );
            $numVal    = (float) str_replace( '%', '', $surchargeRaw );

            if ( $numVal !== 0.0 ) {
                if ( $isPercent ) {
                    $finalAmount = $dbAmount * ( 1 + $numVal / 100 );
                    $absVal      = abs( $numVal );
                    $surchargeLabel = $numVal > 0
                        ? sprintf( 'vrátane príplatku +%s%%', $absVal )
                        : sprintf( 'po zľave -%s%%', $absVal );
                } else {
                    $finalAmount = $dbAmount + $numVal;
                    $absVal      = abs( $numVal );
                    $surchargeLabel = $numVal > 0
                        ? sprintf( 'vrátane príplatku +%s €', number_format( $absVal, 2, ',', ' ' ) )
                        : sprintf( 'po zľave -%s €', number_format( $absVal, 2, ',', ' ' ) );
                }
            }
        }

        $finalAmount = round( $finalAmount, 2 );

        // ── Scope label ──────────────────────────────────────────────────────
        $scopeLabel = match ( $scope ) {
            'child' => 'Dieťa',
            'adult' => 'Dospelý',
            default => '',
        };

        // ── Vekový rozsah – text ─────────────────────────────────────────────
        $ageText = '';
        if ( $ageMin !== null ) {
            $ageText = str_replace( '.', ',', $ageMin );
            if ( $ageMax !== null ) {
                $ageText .= '–' . str_replace( '.', ',', $ageMax );
            } else {
                $ageText .= '+';
            }
            // skloňovanie
            $upper   = $ageMax ?? $ageMin;
            $base    = (int) floor( $upper );
            $ageUnit = match ( true ) {
                $base === 1                 => 'rok',
                $base >= 2 && $base <= 4   => 'roky',
                default                    => 'rokov',
            };
            $ageText .= ' ' . $ageUnit;
        }

        // ── Frequency labels ─────────────────────────────────────────────────
        $freqLabel   = self::FREQUENCY_LABELS[ $frequencyKey ]  ?? esc_html( $frequencyKey );
        $periodLabel = self::FREQUENCY_PERIOD[ $frequencyKey ]  ?? '';

        // ── SVG ikony – načítaj z wp-content/uploads/spa-icons/ ─────────────
        $spaLogoSvg = $this->loadIcon( 'spa-icon' );
        $ageSvg     = $this->loadIcon( 'age' );
        $priceSvg   = $this->loadIcon( 'price-weekly' );
        $freqSvg    = $this->loadIcon( 'frequency' );

        // ════════════════════════════════════════════════════════════════════
        // HTML render
        // ════════════════════════════════════════════════════════════════════
        $html  = '<div class="spa-price-summary">';

        // ── 1. Program ───────────────────────────────────────────────────────
        $html .= '<div class="spa-summary-row spa-summary-program">';
        $html .= '<span class="spa-summary-icon spa-logo-small">' . $spaLogoSvg . '</span>';
        $html .= '<div class="spa-summary-content">';
        $html .= '<strong>Vybraný program:</strong> ' . $programName;
        if ( $scopeLabel ) {
            $html .= ' <span class="spa-summary-scope-badge">/ ' . esc_html( $scopeLabel ) . '</span>';
        }
        // JS placeholder pre meno účastníka (doplní spa-register-gf-scope.js)
        $html .= '<span class="spa-summary-participant-name"></span>';
        $html .= '</div>';
        $html .= '</div>';

        // ── 2. Vekový rozsah ────────────────────────────────────────────────
        if ( $ageText ) {
            $html .= '<div class="spa-summary-row spa-summary-age">';
            $html .= '<span class="spa-summary-icon">' . $ageSvg . '</span>';
            $html .= '<div class="spa-summary-content">';
            $html .= '<strong>' . esc_html( $ageText ) . '</strong>';
            $html .= '</div>';
            $html .= '</div>';

            // JS vloží varovanie o veku sem, ak birthdate je mimo rozsahu
            if ( $ageMin !== null ) {
                $html .= '<div class="spa-summary-age-warning" '
                    . 'data-age-min="' . esc_attr( $ageMin ) . '" '
                    . ( $ageMax !== null ? 'data-age-max="' . esc_attr( $ageMax ) . '"' : '' )
                    . ' style="display:none;">'
                    . '<span class="spa-form-warning">⚠️ Vek účastníka nezodpovedá vybranému programu!</span>'
                    . '</div>';
            }
        }

        // ── 3. Predplatné ────────────────────────────────────────────────────
        if ( $dbAmount > 0 ) {
            $html .= '<div class="spa-summary-row spa-summary-frequency">';
            $html .= '<span class="spa-summary-icon">' . $priceSvg . '</span>';
            $html .= '<div class="spa-summary-content">';
            $html .= '<strong>' . esc_html( $this->formatPrice( $dbAmount ) )
                . ( $periodLabel ? ' ' . esc_html( $periodLabel ) : '' ) . '</strong>';
            $html .= '<br><span class="spa-summary-freq-label">';
            $html .= '<span class="spa-summary-icon-inline">' . $freqSvg . '</span> ';
            $html .= esc_html( 'Tréning ' . $freqLabel );
            $html .= '</span>';
            $html .= '</div>';
            $html .= '</div>';
        }

        // ── 4. Cena k úhrade ─────────────────────────────────────────────────
        if ( $finalAmount > 0 ) {
            $html .= '<div class="spa-summary-row spa-summary-price">';
            $html .= '<span class="spa-summary-icon">' . $priceSvg . '</span>';
            $html .= '<div class="spa-summary-content">';
            $html .= '<strong>Cena k úhrade: '
                . esc_html( $this->formatPrice( $finalAmount ) ) . '</strong>';
            if ( $surchargeLabel ) {
                $html .= ' <span class="spa-summary-surcharge">(' . esc_html( $surchargeLabel ) . ')</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>'; // .spa-price-summary

        return $html;
    }

    // ────────────────────────────────────────────────────────────────────────

    /**
     * Formátovanie ceny – dynamické desatinné miesta (zhodné s formatPriceForCard v JS)
     */
    private function formatPrice( float $amount ): string {
        if ( $amount <= 0 ) {
            return '0 €';
        }
        if ( fmod( $amount, 1.0 ) === 0.0 ) {
            return number_format( $amount, 0, ',', ' ' ) . ' €';
        }
        return number_format( $amount, 2, ',', ' ' ) . ' €';
    }

    /**
     * Načítaj SVG ikonu z uploads/spa-icons/ (rovnaký zdroj ako spa-selection.php)
     * Fallback: prázdny string (nikdy nespôsobí chybu)
     */
    private function loadIcon( string $name ): string {
        $path = WP_CONTENT_DIR . '/uploads/spa-icons/' . $name . '.svg';
        if ( file_exists( $path ) ) {
            return (string) file_get_contents( $path );
        }
        return '';
    }
}