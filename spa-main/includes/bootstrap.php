<?php
/**
 * SPA System Bootstrap
 * Inicializácia pluginu a načítanie závislostí
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definovanie konštánt pluginu – POUŽITIE VLASTNÉHO PREFIXU
if (!defined('SPA_PLUGIN_VERSION')) {
    define('SPA_PLUGIN_VERSION', '1.0.0');
}
if (!defined('SPA_PLUGIN_DIR')) {
    define('SPA_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
}
if (!defined('SPA_PLUGIN_URL')) {
    define('SPA_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
}
if (!defined('SPA_CONFIG_DIR')) {
    define('SPA_CONFIG_DIR', SPA_PLUGIN_DIR . 'spa-config/');
}

// Spätná kompatibilita - ak téma nepoužíva SPA_VERSION
if (!defined('SPA_VERSION')) {
    define('SPA_VERSION', SPA_PLUGIN_VERSION);
}

/**
 * Načítanie konfigurácie field mappingu
 * Vracia array s mapovaním logických názvov na GF input_ID
 */
function spa_load_field_config() {
    $config_file = SPA_CONFIG_DIR . 'fields.php';
    
    if (!file_exists($config_file)) {
        return [];
    }
    
    return include $config_file;
}

/**
 * Kontrola závislostí
 * Overuje, či je Gravity Forms aktívny
 */
function spa_check_dependencies() {
    if (!class_exists('GFForms')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>SPA System:</strong> Plugin vyžaduje aktívny Gravity Forms.';
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Inicializácia pluginu
 */
function spa_init() {
    // Kontrola závislostí
    if (!spa_check_dependencies()) {
        return;
    }
    
    // Načítanie core súborov
    require_once SPA_PLUGIN_DIR . 'includes/spa-helpers.php';
    require_once SPA_PLUGIN_DIR . 'includes/spa-core.php';
    require_once SPA_PLUGIN_DIR . 'includes/spa-registration.php';
    require_once SPA_PLUGIN_DIR . 'includes/spa-infobox.php';
    
    // Inicializácia registračného modulu
    spa_registration_init();
    
    // Inicializácia infobox modulu
    spa_infobox_init();
}

add_action('plugins_loaded', 'spa_init', 5); // Priorita 5 = skoršie ako téma

/**
 * Enqueue JavaScript pre Gravity Forms
 */
function spa_enqueue_gf_scripts($form) {
    // Načítanie field mappingu z spa-config
    $field_config = spa_load_field_config();
    
    // Enqueue hlavného JS súboru
    wp_enqueue_script(
        'spa-registration',
        SPA_PLUGIN_URL . 'assets/js/spa-registration-summary.js',
        ['jquery'],
        SPA_PLUGIN_VERSION,
        true
    );
    
    // Enqueue infobox JS
    wp_enqueue_script(
        'spa-infobox',
        SPA_PLUGIN_URL . 'assets/js/spa-infobox.js',
        ['spa-registration'],
        SPA_PLUGIN_VERSION,
        true
    );
    
    // Poskytnutie konfigurácie do JS
    wp_localize_script('spa-registration', 'spaConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'fields' => [
            'spa_city' => $field_config['spa_city'] ?? '',
            'spa_program' => $field_config['spa_program'] ?? '',
        ],
        'nonce' => wp_create_nonce('spa_ajax_nonce'),
    ]);
}
add_action('gform_enqueue_scripts', 'spa_enqueue_gf_scripts', 10, 1);