<?php
namespace SpaRegisterGf\Services;

use SpaRegisterGf\Infrastructure\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Prepočíta cenu z DB podľa program_id + frequency_key.
 * Ak recalculated_amount !== session.amount → blokujúca chyba.
 *
 * Postmeta kľúče pre ceny (z SPA-DATA-MAP.md):
 *   spa_price_1x_weekly
 *   spa_price_2x_weekly
 *   spa_price_monthly
 *   spa_price_semester
 *
 * external_surcharge formáty (z SPA-SELECTION-FLOW.md):
 *   "-10%"  → percentuálna zľava
 *   "+10%"  → percentuálny príplatok
 *   "10"    → fixná suma EUR
 *   "-10"   → fixná zľava EUR
 */
class AmountVerificationService {

    /**
     * Overí, či session.amount zodpovedá prepočítanej cene z DB.
     *
     * @return bool  true = suma súhlasí, false = mismatch (blokujúce)
     */
    public function verify( SessionService $session ): bool {
        $programId    = $session->getProgramId();
        $frequencyKey = $session->getFrequencyKey();
        $sessionAmount = $session->getAmount();
        $surcharge    = $session->getExternalSurcharge();

        if ( $programId <= 0 || empty( $frequencyKey ) ) {
            Logger::error( 'amount_verify_missing_params', [
                'program_id'    => $programId,
                'frequency_key' => $frequencyKey,
            ] );
            return false;
        }

        // Čítaj base cenu z postmeta spa_group CPT
        $basePrice = $this->getBasePrice( $programId, $frequencyKey );

        if ( $basePrice === null ) {
            Logger::error( 'amount_verify_price_not_found', [
                'program_id'    => $programId,
                'frequency_key' => $frequencyKey,
            ] );
            return false;
        }

        // Pripočítaj surcharge ak existuje
        $recalculated = $this->applySurcharge( $basePrice, $surcharge );

        // Porovnaj (tolerance 0.01 EUR kvôli float zaokrúhľovaniu)
        $matches = abs( $recalculated - $sessionAmount ) < 0.01;

        if ( ! $matches ) {
            Logger::warning( 'amount_verify_mismatch', [
                'session_amount'  => $sessionAmount,
                'recalculated'    => $recalculated,
                'base_price'      => $basePrice,
                'surcharge'       => $surcharge,
                'program_id'      => $programId,
                'frequency_key'   => $frequencyKey,
            ] );
        }

        return $matches;
    }

    // ── Interné metódy ───────────────────────────────────────────────────────

    /**
     * Načíta base cenu z postmeta spa_group.
     * Frequency key je zároveň postmeta kľúč (spa_price_1x_weekly, atď.)
     */
    private function getBasePrice( int $programId, string $frequencyKey ): ?float {
        // Povolené kľúče podľa SPA-DATA-MAP.md
        $allowed = [
            'spa_price_1x_weekly',
            'spa_price_2x_weekly',
            'spa_price_monthly',
            'spa_price_semester',
        ];

        if ( ! in_array( $frequencyKey, $allowed, true ) ) {
            Logger::warning( 'amount_verify_unknown_frequency', [ 'key' => $frequencyKey ] );
            return null;
        }

        $meta = get_post_meta( $programId, $frequencyKey, true );

        if ( $meta === '' || $meta === false ) {
            return null;
        }

        return (float) $meta;
    }

    /**
     * Aplikuje external_surcharge na base cenu.
     *
     * Formáty (SPA-SELECTION-FLOW.md):
     *   "-10%"  → zľava 10 %
     *   "+10%"  → príplatok 10 %
     *   "10"    → fixný príplatok +10 EUR
     *   "-10"   → fixná zľava -10 EUR
     */
    private function applySurcharge( float $base, ?string $surcharge ): float {
        if ( $surcharge === null || $surcharge === '' ) {
            return $base;
        }

        if ( str_contains( $surcharge, '%' ) ) {
            // Percentuálny model
            $pct  = (float) str_replace( [ '%', '+' ], '', $surcharge );
            return round( $base + ( $base * $pct / 100 ), 2 );
        }

        // Fixný model
        $fixed = (float) $surcharge;
        return round( $base + $fixed, 2 );
    }
}