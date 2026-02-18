<?php
namespace SpaRegisterGf\Hooks;

use SpaRegisterGf\Infrastructure\GFFormFinder;
use SpaRegisterGf\Services\SessionService;
use SpaRegisterGf\Services\FieldMapService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PreRenderHooks {

    private const CHILD_ONLY_KEYS = [
        'spa_guardian_name_first',
        'spa_guardian_name_last',
        'spa_parent_email',
        'spa_parent_phone',
        'spa_consent_guardian',
    ];

    private const ADULT_ONLY_KEYS = [
        'spa_client_email_required',
    ];

    public function handle( array $form ): array {

        error_log( '[PRE_RENDER] handle() CALLED. Form cssClass: ' . ( $form['cssClass'] ?? 'EMPTY' ) );

        if ( ! GFFormFinder::guard( $form ) ) {
            error_log( '[PRE_RENDER] guard() = FALSE → return without changes' );
            return $form;
        }

        error_log( '[PRE_RENDER] guard() = TRUE' );

        $session = SessionService::tryCreate();

        if ( ! $session ) {
            error_log( '[PRE_RENDER] session = NULL → return without changes' );
            return $form;
        }

        error_log( '[PRE_RENDER] session OK' );

        try {
            $scope = $session->getScope();
        } catch ( \RuntimeException $e ) {
            error_log( '[PRE_RENDER] getScope() exception: ' . $e->getMessage() );
            return $form;
        }

        error_log( '[PRE_RENDER] scope = ' . $scope );

        if ( ! in_array( $scope, [ 'adult', 'child' ], true ) ) {
            error_log( '[PRE_RENDER] scope invalid → return without changes' );
            return $form;
        }

        $hideKeys = $scope === 'adult' ? self::CHILD_ONLY_KEYS : self::ADULT_ONLY_KEYS;

        $hideBaseIds = [];
        foreach ( $hideKeys as $key ) {
            $fieldId = FieldMapService::tryResolve( $key );
            if ( $fieldId === null ) {
                error_log( '[PRE_RENDER] tryResolve(' . $key . ') = NULL → skip' );
                continue;
            }
            $normalized  = preg_replace( '/^input_/i', '', $fieldId );
            $baseId      = explode( '.', $normalized )[0];
            $hideBaseIds[] = $baseId;
            error_log( '[PRE_RENDER] will hide baseId=' . $baseId . ' (from key=' . $key . ', fieldId=' . $fieldId . ')' );
        }

        if ( empty( $hideBaseIds ) ) {
            error_log( '[PRE_RENDER] hideBaseIds empty → return without changes' );
            return $form;
        }

        foreach ( $form['fields'] as &$field ) {
            if ( in_array( (string) $field->id, $hideBaseIds, true ) ) {
                $field->isHidden = true;
                error_log( '[PRE_RENDER] field->id=' . $field->id . ' SET isHidden=true' );
            }
        }
        unset( $field );

        error_log( '[PRE_RENDER] done. Returning modified form.' );

        return $form;
    }
}