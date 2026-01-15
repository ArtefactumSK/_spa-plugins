<?php
/**
 * Plugin Name: SPA System MAIN
 * Plugin URI: https://artefactum.sk/spa
 * Description: Komplexný registračný a manažérsky systém pre športovú akadémiu
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Artefactum
 * Author URI: https://artefactum.sk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: spa-main
 * Domain Path: /languages
 */

// Zabránenie priamemu prístupu
if (!defined('ABSPATH')) {
    exit;
}

// Definovanie základných konštánt pluginu
if (!defined('SPA_VERSION')) {
    define('SPA_VERSION', '1.0.0');
}
define('SPA_PLUGIN_FILE', __FILE__);
define('SPA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Načítanie bootstrap súboru
require_once SPA_PLUGIN_DIR . 'includes/bootstrap.php';