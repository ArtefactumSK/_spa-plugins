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

            if ( in_array( $scope, [ 'adult', 'child' ], true ) ) {
                wp_add_inline_script(
                    'spa-register-gf-js',
                    'window.spaRegisterScope = "' . esc_js( $scope ) . '";',
                    'before'
                );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[spa-register-gf] scope=' . $scope );
                }
            }

            if ( in_array( $scope, [ 'adult', 'child' ], true ) ) {
                wp_add_inline_script(
                    'spa-register-gf-js',
                    'window.spaRegisterScope = "' . esc_js( $scope ) . '";',
                    'before'
                );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[spa-register-gf] scope=' . $scope );
                }
            }

            $freqFieldId = FieldMapService::tryResolve( 'spa_frequency' );
            if ( $freqFieldId && ! empty( $session->getFrequencyKey() ) ) {
                add_filter( 'gform_field_value_' . $freqFieldId, function () use ( $session ) {
                    return $session->getFrequencyKey();
                } );
            }

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
            }
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

    private function buildPriceSummary( SessionService $session ): string {
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
            return '';
        }

        $post = get_post( $programId );
        if ( ! $post || $post->post_status !== 'publish' ) {
            return '';
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

        if ( $surchargeRaw !== '' && $surchargeRaw !== '0' ) {
            $isPercent = str_ends_with( $surchargeRaw, '%' );
            $numVal    = (float) str_replace( '%', '', $surchargeRaw );
            if ( $numVal !== 0.0 ) {
                if ( $isPercent ) {
                    $finalAmount    = $dbAmount * ( 1 + $numVal / 100 );
                    $absVal         = abs( $numVal );
                    $surchargeLabel = $numVal > 0
                        ? 'vrátane príplatku +' . $absVal . '%'
                        : 'po zľave -' . $absVal . '%';
                } else {
                    $finalAmount    = $dbAmount + $numVal;
                    $absVal         = abs( $numVal );
                    $surchargeLabel = $numVal > 0
                        ? 'vrátane príplatku +' . number_format( $absVal, 2, ',', ' ' ) . ' €'
                        : 'po zľave -' . number_format( $absVal, 2, ',', ' ' ) . ' €';
                }
            }
        }
        $finalAmount = round( $finalAmount, 2 );

        // ── Scope label ──────────────────────────────────────────────────────
        $scopeLabel = match ( $scope ) {
            'child' => 'Vybraný program pre deti vyžaduje údaje o zákonom zástupcom dieťaťa',
            'adult' => 'Váš vybraný program',
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

        // 1. Program + scope  (ikona: spa_logo – rovnaká ako v renderFullInfobox)
        $html .= '<li class="spa-summary-item spa-summary-program">';
        $html .= '<span class="spa-summary-icon">' . $iconLogo . '</span>';
        $html .= '<span><strong>' . $programName . '</strong>';
        if ( $scopeLabel ) {
            $html .= '<br>' . esc_html( $scopeLabel );
        }
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
                $html .= '<span class="spa-form-warning"'
                    . ' data-age-min="' . esc_attr( (string) $ageMin ) . '"'
                    . ( $ageMax !== null ? ' data-age-max="' . esc_attr( (string) $ageMax ) . '"' : '' )
                    . ' style="display:none;">⚠️ Vek účastníka nezodpovedá vybranému programu!</span>';
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
            if ( $periodLabel ) {
                $html .= ' ' . esc_html( $periodLabel );
            }
            $html .= '</strong>';
            $html .= '</li>';

            $html .= '<li class="spa-summary-item spa-summary-frequency">';
            $html .= '<span class="spa-summary-icon">' . $iconFreq . '</span>';
            $html .= 'Tréning ' . esc_html( $freqLabel );
            $html .= '</li>';
        }

        // 6. Cena k úhrade
        if ( $finalAmount > 0 ) {
            $html .= '<li class="spa-summary-item spa-summary-price">';
            $html .= '<span class="spa-summary-icon">' . $iconPrice . '</span>';
            $html .= '<strong>Cena k úhrade: ' . esc_html( $this->formatPrice( $finalAmount ) ) . '</strong>';
            if ( $surchargeLabel ) {
                $html .= ' <span class="spa-summary-surcharge">(' . esc_html( $surchargeLabel ) . ')</span>';
            }
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
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
     * Formátovanie ceny – zhodné s formatPriceForCard() v spa-selection-pricing.js
     */
    private function formatPrice( float $amount ): string {
        if ( $amount <= 0 ) return '0 €';
        return fmod( $amount, 1.0 ) === 0.0
            ? number_format( $amount, 0, ',', ' ' ) . ' €'
            : number_format( $amount, 2, ',', ' ' ) . ' €';
    }
}