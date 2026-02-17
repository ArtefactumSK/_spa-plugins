<?php
 /**
  * Plugin Name: SPA Register GF
  * Description: SPA registrácia cez Gravity Forms na základe $_SESSION['spa_registration'] (spa-selection → /register).
  * Version: 0.1.0
  */
 
 if ( ! defined( 'ABSPATH' ) ) { exit; }
 
 define( 'SPA_REG_GF_FILE', __FILE__ );
 define( 'SPA_REG_GF_DIR', plugin_dir_path( __FILE__ ) );
 define( 'SPA_REG_GF_URL', plugin_dir_url( __FILE__ ) );
 
 // TTL pre session expiry (30 min)
 define( 'SPA_REG_GF_SESSION_TTL', 1800 );
 
 require_once SPA_REG_GF_DIR . 'src/Bootstrap/Plugin.php';
 
 register_activation_hook( __FILE__, function () {
     // Reset cache/ transientov, ak ich používa GFFormFinder (bezpečné aj keď zatiaľ neexistujú)
     delete_transient( 'spa_reg_gf_form_id' );
 
     // Voliteľné: uložiť verziu (ak chceš)
     update_option( 'spa_reg_gf_version', '0.1.0' );
 } );
 
 register_deactivation_hook( __FILE__, function () {
     delete_transient( 'spa_reg_gf_form_id' );
 } );
 
 // Boot až keď sú pluginy načítané (GF sa načítava ako plugin)
 add_action( 'plugins_loaded', function () {
     \SpaRegisterGf\Bootstrap\Plugin::boot();
 }, 20 );
 


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SPA_REG_GF_FILE',    __FILE__ );
define( 'SPA_REG_GF_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SPA_REG_GF_URL',     plugin_dir_url( __FILE__ ) );
define( 'SPA_REG_GF_VERSION', '1.0.0' );
define( 'SPA_REG_GF_SESSION_KEY',  'spa_registration' );
define( 'SPA_REG_GF_SESSION_TTL',  1800 );   // 30 minút v sekundách
define( 'SPA_REG_GF_CSS_CLASS',    'spa-register-gf' );
define( 'SPA_REG_GF_SELECTOR_URL', '/spa-selector' );

require_once SPA_REG_GF_DIR . 'src/Bootstrap/Plugin.php';

add_action( 'plugins_loaded', function () {
    SpaRegisterGf\Bootstrap\Plugin::boot();
} );