<?php
namespace SpaRegisterGf\Hooks;

use SpaRegisterGf\Infrastructure\GFFormFinder;
use SpaRegisterGf\Services\FieldMapService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EnqueueHooks {

    public function register(): void {
        add_action( 'gform_enqueue_scripts', [ $this, 'enqueue' ], 10, 2 );
    }

    public function enqueue( array $form, bool $is_ajax ): void {
        if ( ! GFFormFinder::guard( $form ) ) {
            return;
        }

        wp_enqueue_script(
            'spa-register-gf-js',
            SPA_REG_GF_URL . 'assets/js/spa-register-gf-scope.js',
            [],
            SPA_REG_GF_VERSION,
            true
        );

        wp_localize_script(
            'spa-register-gf-js',
            'spaRegisterFields',
            FieldMapService::getAll()
        );
    }
}