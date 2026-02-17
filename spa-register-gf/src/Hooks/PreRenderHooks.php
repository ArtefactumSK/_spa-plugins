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
}