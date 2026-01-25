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

/**
 * Registrácia frontend skriptov a konfigurácie
 */
add_action('wp_enqueue_scripts', 'spa_enqueue_infobox_scripts');

function spa_enqueue_infobox_scripts() {
    // JavaScript
    wp_enqueue_script(
        'spa-infobox-js',
        plugin_dir_url(__FILE__) . 'assets/js/spa-infobox.js',
        ['jquery'],
        '1.0.0',
        true
    );

    // Načítaj field mapping
    $fields_config = include(plugin_dir_path(__FILE__) . 'spa-config/fields.php');

    // Posli do JS
    /* wp_localize_script('spa-infobox-js', 'spaConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'fields' => [
            'spa_city' => $fields_config['spa_city'],
            'spa_program' => $fields_config['spa_program'],
        ]
    ]); */
    wp_localize_script('spa-infobox-js', 'spaConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'fields'  => $fields_config,
    ]);
}