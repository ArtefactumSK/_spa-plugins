<?php
/**
 * Plugin Name: SPA Register GF
 * Description: SPA registrácia cez Gravity Forms na základe $_SESSION['spa_registration'] (spa-selection → /register).
 * Version: 1.0.0
 * Author: SPA System
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Guard proti dvojitému načítaniu
if ( defined( 'SPA_REG_GF_VERSION' ) ) {
    return;
}

define( 'SPA_REG_GF_FILE',         __FILE__ );
define( 'SPA_REG_GF_DIR',          plugin_dir_path( __FILE__ ) );
define( 'SPA_REG_GF_URL',          plugin_dir_url( __FILE__ ) );
define( 'SPA_REG_GF_VERSION',      '1.0.0' );
define( 'SPA_REG_GF_SESSION_KEY',  'spa_registration' );
define( 'SPA_REG_GF_SESSION_TTL',  1800 );
define( 'SPA_REG_GF_CSS_CLASS',    'spa-register-gf' );
define( 'SPA_REG_GF_SELECTOR_URL', '/spa-selector' );

require_once SPA_REG_GF_DIR . 'src/Bootstrap/Plugin.php';

register_activation_hook( __FILE__, function () {
    delete_transient( 'spa_reg_gf_form_id' );
    update_option( 'spa_reg_gf_version', '1.0.0' );
} );

register_deactivation_hook( __FILE__, function () {
    delete_transient( 'spa_reg_gf_form_id' );
} );

add_action( 'plugins_loaded', function () {
    SpaRegisterGf\Bootstrap\Plugin::boot();
}, 20 );