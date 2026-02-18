<?php
/**
 * Hook: gform_pre_render
 *
 * Predvyplnenie polí formulára podľa priority:
 *   SESSION > GET
 * GET je iba UI fallback pre spa_city a spa_program.
 * Plugin SESSION NESMIE meniť.
 */

namespace SpaRegisterGf\Hooks;

use SpaRegisterGf\Infrastructure\GFFormFinder;
use SpaRegisterGf\Services\SessionService;
use SpaRegisterGf\Services\FieldMapService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PreRenderHooks {

    public function handle( array $form ): array {
        if ( ! GFFormFinder::guard( $form ) ) {
            return $form;
        }

        $session = SessionService::tryCreate();

        if ( ! $session ) {
            return $form;
        }

        try {
            $scope = $session->getScope();
        } catch ( \RuntimeException $e ) {
            return $form;
        }

        if ( ! in_array( $scope, [ 'adult', 'child' ], true ) ) {
            return $form;
        }

        $childOnlyKeys = [
            'spa_guardian_name_first',
            'spa_guardian_name_last',
            'spa_parent_email',
            'spa_parent_phone',
            'spa_consent_guardian',
        ];

        $adultOnlyKeys = [
            'spa_client_email_required',
        ];

        $hideKeys = $scope === 'adult' ? $childOnlyKeys : $adultOnlyKeys;

        $hiddenFieldIds = [];
        foreach ( $hideKeys as $key ) {
            $fieldId = FieldMapService::tryResolve( $key );
            if ( $fieldId !== null ) {
                $hiddenFieldIds[] = (string) $fieldId;
            }
        }

        if ( empty( $hiddenFieldIds ) ) {
            return $form;
        }

        foreach ( $form['fields'] as &$field ) {
            $fieldId = (string) $field->id;
            foreach ( $hiddenFieldIds as $hiddenId ) {
                $normalized = preg_replace( '/^input_/', '', $hiddenId );
                $compareId  = strpos( $normalized, '.' ) !== false
                    ? explode( '.', $normalized )[0]
                    : $normalized;
                if ( $fieldId === $compareId ) {
                    $field->isHidden = true;
                    break;
                }
            }
        }
        unset( $field );

        return $form;
    }
}