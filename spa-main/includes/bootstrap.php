<?php
/**
 * SPA System MAIN  Bootstrap
 * Inicializácia pluginu a načítanie závislostí
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definovanie konštánt pluginu
define('SPA_VERSION', '1.0.0');
define('SPA_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
define('SPA_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
define('SPA_CONFIG_DIR', SPA_PLUGIN_DIR . 'spa-config/');

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
    
    // Inicializácia registračného modulu
    spa_registration_init();
}

add_action('plugins_loaded', 'spa_init');

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
        SPA_VERSION,
        true
    );
    
    // Poskytnutie konfigurácie do JS
    wp_localize_script('spa-registration', 'spaConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'fields' => [
            'city' => $field_config['city'] ?? '',
            'program' => $field_config['program'] ?? '',
        ],
        'nonce' => wp_create_nonce('spa_ajax_nonce'),
    ]);
}
add_action('gform_enqueue_scripts', 'spa_enqueue_gf_scripts', 10, 1);