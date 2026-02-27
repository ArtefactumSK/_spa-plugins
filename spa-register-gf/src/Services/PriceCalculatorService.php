<?php
namespace SpaRegisterGf\Services;

use SpaRegisterGf\Infrastructure\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Zdieľaná kalkulačná služba pre výpočet očakávanej prvej platby.
 *
 * Zdroj pravdy:
 *  - program_id       (SESSION)
 *  - frequency_key    (SESSION)
 *  - amount           (SESSION – base amount z DB, fallback)
 *  - external_surcharge (SESSION)
 *
 * Prepočet zodpovedá logike v PreRenderHooks::buildPriceSummary().
 */
class PriceCalculatorService {

    /**
     * @return array{dbAmount: float, finalAmount: float, hasSurcharge: bool, surchargeLabel: string}
     */
    public function calculate( SessionService $session ): array {
        $programId    = $session->getProgramId();
        $frequencyKey = $session->getFrequencyKey();
        $baseAmount   = $session->getAmount();
        $surchargeRaw = $session->getExternalSurcharge() ?? '';

        if ( $programId <= 0 ) {
            Logger::warning( 'price_calc_program_invalid', [
                'program_id' => $programId,
            ] );

            return [
                'dbAmount'       => 0.0,
                'finalAmount'    => 0.0,
                'hasSurcharge'   => false,
                'surchargeLabel' => '',
            ];
        }

        // ── Cena z DB podľa frequency_key ─────────────────────────────────────
        $dbAmount = $baseAmount;

        if ( $frequencyKey !== '' ) {
            $metaVal = get_post_meta( $programId, $frequencyKey, true );
            if ( $metaVal !== '' && $metaVal !== null ) {
                $dbAmount = (float) $metaVal;
            }
        }

        // ── Aplikácia surcharge ───────────────────────────────────────────────
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

        // Zaokrúhlenie na 0.1 EUR – zhodné s PreRenderHooks
        $finalAmount = round( $finalAmount * 10 ) / 10;

        return [
            'dbAmount'       => (float) $dbAmount,
            'finalAmount'    => (float) $finalAmount,
            'hasSurcharge'   => (bool) $hasSurcharge,
            'surchargeLabel' => (string) $surchargeLabel,
        ];
    }
}

