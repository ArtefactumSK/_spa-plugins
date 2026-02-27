<?php
namespace SpaRegisterGf\Services;

use SpaRegisterGf\Infrastructure\GFEntryReader;
use SpaRegisterGf\Infrastructure\Logger;
use SpaRegisterGf\Services\FieldMapService;
use SpaRegisterGf\Services\PriceCalculatorService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Server-side verifikácia sumy bez použitia POST – iba cez session + GF entry.
 */
class AmountVerificationService {

    /**
     * Overí, či suma z GF entry zodpovedá prepočítanej cene z DB cez session.
     *
     * @return bool  true = suma súhlasí, false = mismatch (blokujúce)
     */
    public function verify( SessionService $session, array $entry ): bool {
        $debug = defined( 'SPA_REGISTER_DEBUG' ) && SPA_REGISTER_DEBUG;

        // ── Session – musí existovať a obsahovať kľúčové hodnoty ──────────────
        $programId    = $session->getProgramId();
        $frequencyKey = $session->getFrequencyKey();
        $amount       = $session->getAmount();
        $surchargeRaw = $session->getExternalSurcharge();
        $createdAt    = $session->getCreatedAt();

        $scope        = null;
        $scopeValid   = true;
        try {
            $scope = $session->getScope();
        } catch ( \RuntimeException $e ) {
            $scopeValid = false;
        }

        if ( $programId <= 0 || empty( $frequencyKey ) || $amount <= 0 || ! $scopeValid ) {
            Logger::error( 'amount_verify_missing_session', [
                'program_id'    => $programId,
                'frequency_key' => $frequencyKey,
                'amount'        => $amount,
                'scope_valid'   => $scopeValid,
            ] );
            return false;
        }

        // Detailný log session stavu
        Logger::info( 'amount_verify_server_session', [
            'program_id'         => $programId,
            'frequency_key'      => $frequencyKey,
            'amount'             => $amount,
            'external_surcharge' => $surchargeRaw,
            'scope'              => $scope,
            'created_at'         => $createdAt,
        ] );

        // ── Očakávaná suma – zdieľaná kalkulácia (zhodná s PreRenderHooks) ────
        $calculator = new PriceCalculatorService();
        $calc       = $calculator->calculate( $session );
        $expected   = (float) $calc['finalAmount'];

        Logger::info( 'amount_verify_server_expected', [
            'expected_amount' => $expected,
            'db_amount'       => $calc['dbAmount'],
            'has_surcharge'   => $calc['hasSurcharge'],
            'surcharge_label' => $calc['surchargeLabel'],
        ] );

        // ── Pokus o prečítanie "posted" sumy z GF entry (nie z POST) ──────────
        $entryReader = new GFEntryReader( $entry );
        $postedRaw   = $entryReader->tryGetText( 'spa_first_payment_amount' );

        $hasNumericPosted = false;
        $postedAmount     = null;
        $postedSource     = null;

        if ( $postedRaw !== null && $postedRaw !== '' ) {
            // Normalize "46,50" / "46.50" / "46 500" → float
            $normalized = str_replace( [ ' ', ',' ], [ '', '.' ], $postedRaw );
            if ( is_numeric( $normalized ) ) {
                $hasNumericPosted = true;
                $postedAmount     = (float) $normalized;
                $postedSource     = 'gf_entry:spa_first_payment_amount';
            }
        }

        Logger::info( 'amount_verify_post_amount_state', [
            'has_numeric' => $hasNumericPosted,
            'source'      => $postedSource,
            'raw_value'   => $postedRaw,
        ] );

        // Ak nevieme spoľahlivo prečítať numerickú hodnotu z entry,
        // NEBLOKUJEME registráciu – session je autoritatívna.
        if ( ! $hasNumericPosted ) {
            if ( $debug ) {
                Logger::info( 'amount_verify_no_post_amount', [
                    'reason' => 'no_numeric_value_in_entry',
                ] );
            }

            return true;
        }

        // ── Porovnanie hodnôt ────────────────────────────────────────────────
        $diff    = abs( (float) $postedAmount - (float) $expected );
        $matches = $diff < 0.01; // tolerancia na centy

        if ( ! $matches ) {
            $payload = [
                'expected'        => $expected,
                'posted'          => $postedAmount,
                'diff'            => $diff,
                'program_id'      => $programId,
                'frequency_key'   => $frequencyKey,
                'scope'           => $scope,
                'external_amount' => $amount,
            ];

            if ( $debug ) {
                $payload['debug_msg'] = sprintf(
                    'DEBUG: expected=%.2f posted=%.2f diff=%.4f',
                    $expected,
                    $postedAmount,
                    $diff
                );
            }

            Logger::warning( 'amount_verify_mismatch', $payload );
            return false;
        }

        return true;
    }
}