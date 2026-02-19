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

    public function handle( array $form ): array {
        if ( ! GFFormFinder::guard( $form ) ) {
            return $form;
        }

        $session = SessionService::tryCreate();

        // Predvyplnenie spa_city: SESSION nie je zdrojom (city nie je v session)
        // Fallback z GET (len UI, neovplyvňuje amount/scope/frequency)
        $cityFieldId    = FieldMapService::tryResolve( 'spa_city' );
        $programFieldId = FieldMapService::tryResolve( 'spa_program' );

        if ( $session ) {
            // session.program_id predvyplní spa_program
            if ( $programFieldId && $session->getProgramId() > 0 ) {
                add_filter( 'gform_field_value_' . $programFieldId, function () use ( $session ) {
                    return $session->getProgramId();
                } );
            }

            // spa_resolved_type predvyplníme zo session.scope
            $resolvedTypeFieldId = FieldMapService::tryResolve( 'spa_resolved_type' );
            if ( $resolvedTypeFieldId ) {
                try {
                    $scope = $session->getScope();
                    add_filter( 'gform_field_value_' . $resolvedTypeFieldId, function () use ( $scope ) {
                        return $scope;
                    } );
                } catch ( \RuntimeException $e ) {
                    // scope chýba – formulár sa zobrazí bez predvyplnenia
                }
            }

            // spa_frequency zo session.frequency_key
            $freqFieldId = FieldMapService::tryResolve( 'spa_frequency' );
            if ( $freqFieldId && ! empty( $session->getFrequencyKey() ) ) {
                add_filter( 'gform_field_value_' . $freqFieldId, function () use ( $session ) {
                    return $session->getFrequencyKey();
                } );
            }

            // Inject price summary do HTML field s cssClass 'info_price_summary'
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

        // GET fallback – iba spa_city a spa_program, ak session neexistuje alebo program_id je 0
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

    // ── Price Summary ────────────────────────────────────────────────────────

    private function buildPriceSummary( SessionService $session ): string {
        $programId    = $session->getProgramId();
        $frequencyKey = $session->getFrequencyKey();
        $amount       = method_exists( $session, 'getAmount' )          ? $session->getAmount()          : null;
        $external     = method_exists( $session, 'getExternalSurcharge' ) ? $session->getExternalSurcharge() : null;

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

        // Mesto z post meta (kľúče overené v projekte)
        $city = '';
        foreach ( [ 'spa_city', 'city', 'spa_program_city' ] as $metaKey ) {
            $val = get_post_meta( $programId, $metaKey, true );
            if ( ! empty( $val ) ) {
                $city = esc_html( $val );
                break;
            }
        }

        // ── Finálna cena ─────────────────────────────────────────────────────
        $priceHtml = '';
        if ( $amount !== null && $amount > 0 ) {
            $finalPrice = (float) $amount;

            if ( $external !== null && $external !== '' ) {
                $ext = (string) $external;
                if ( strpos( $ext, '%' ) !== false ) {
                    $pct        = (float) str_replace( '%', '', $ext );
                    $finalPrice = $finalPrice * ( 1 + $pct / 100 );
                } else {
                    $finalPrice = $finalPrice + (float) $ext;
                }
            }

            $finalPrice = round( $finalPrice, 2 );
            $priceHtml  = number_format( $finalPrice, 2, ',', ' ' ) . ' €';
        }

        // ── Scope label ──────────────────────────────────────────────────────
        $scopeLabel = match ( $scope ) {
            'child' => 'Dieťa',
            'adult' => 'Dospelý',
            default => '',
        };

        // ── Frequency label ──────────────────────────────────────────────────
        $freqLabel = ! empty( $frequencyKey ) ? esc_html( $frequencyKey ) : '';

        // ── HTML ─────────────────────────────────────────────────────────────
        $html  = '<div class="spa-price-summary">';

        $html .= '<div class="spa-summary-program">';
        $html .= '<strong>Vybraný program:</strong> ' . $programName;
        if ( $city ) {
            $html .= ' &ndash; ' . $city;
        }
        // JS doplní meno účastníka do tohto placeholderu
        $html .= '<span class="spa-summary-participant-name"></span>';
        $html .= '</div>';

        if ( $freqLabel ) {
            $html .= '<div class="spa-summary-frequency">';
            $html .= '<strong>Vybrané predplatné:</strong> ' . $freqLabel;
            $html .= '</div>';
        }

        if ( $scopeLabel ) {
            $html .= '<div class="spa-summary-scope">';
            $html .= '<strong>Typ účastníka:</strong> ' . $scopeLabel;
            $html .= '</div>';
        }

        if ( $priceHtml ) {
            $html .= '<div class="spa-summary-price">';
            $html .= '<strong>Cena:</strong> ' . $priceHtml;
            $html .= '</div>';
        }

        $html .= '</div>'; // .spa-price-summary

        return $html;
    }
}