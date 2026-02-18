<?php
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

        wp_add_inline_script(
            'spa-register-gf-js',
            'window.spaRegisterScope = "' . esc_js( $scope ) . '";',
            'before'
        );

        return $form;
    }
}