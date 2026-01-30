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
    // JavaScript - Orchestrator (must be enqueued first for localize)
    wp_enqueue_script(
        'spa-infobox-orchestrator',
        SPA_PLUGIN_URL . 'assets/js/spa-infobox-orchestrator.js',
        ['jquery'],
        '1.0.0',
        true
    );

    // JavaScript - Other modules
    wp_enqueue_script(
        'spa-infobox-js',
        SPA_PLUGIN_URL . 'assets/js/spa-infobox.js',
        ['jquery', 'spa-infobox-orchestrator'],
        '1.0.0',
        true
    );

    // Load fields registry from JSON and inject into runtime
    $fields_json_path = SPA_PLUGIN_DIR . 'spa-config/fields.json';
    $fields_registry = [];
    if (file_exists($fields_json_path)) {
        $json_content = file_get_contents($fields_json_path);
        $fields_registry = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[SPA] Failed to parse fields.json: ' . json_last_error_msg());
            $fields_registry = [];
        }
    } else {
        error_log('[SPA] fields.json not found at: ' . $fields_json_path);
    }

    // Inject fields registry before orchestrator runs (via inline script)
    wp_localize_script(
        'spa-infobox-orchestrator',
        'spaFieldsRegistry',
        $fields_registry
    );

    // Load PHP-based fields config (legacy/runtime overrides)
    $fields_config = include(SPA_PLUGIN_DIR . 'spa-config/fields.php');

    // Send config to JS (merged with registry in orchestrator)
    wp_localize_script('spa-infobox-js', 'spaConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'fields'  => $fields_config,
    ]);
}