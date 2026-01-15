<?php
/**
 * SPA System Bootstrap
 */

if (!defined('ABSPATH')) {
    exit;
}

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

function spa_load_field_config() {
    $config_file = SPA_CONFIG_DIR . 'fields.php';
    if (!file_exists($config_file)) {
        return [];
    }
    return include $config_file;
}

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

function spa_init() {
    if (!spa_check_dependencies()) {
        return;
    }
    
    require_once SPA_PLUGIN_DIR . 'includes/spa-helpers.php';
    require_once SPA_PLUGIN_DIR . 'includes/spa-core.php';
    require_once SPA_PLUGIN_DIR . 'includes/spa-registration.php';
    require_once SPA_PLUGIN_DIR . 'includes/spa-infobox.php';
    
    spa_registration_init();
    spa_infobox_init();

    require_once SPA_PLUGIN_DIR . 'includes/spa-user-create.php';
    require_once SPA_PLUGIN_DIR . 'includes/spa-user-management.php';
    spa_user_management_init();
}

add_action('plugins_loaded', 'spa_init', 5);

/**
 * Enqueue scripts - MINIMÁLNE
 */
add_action('wp_enqueue_scripts', 'spa_enqueue_scripts', 20);

function spa_enqueue_scripts() {
    if (is_admin()) {
        return;
    }
    
    wp_enqueue_script(
        'spa-registration',
        SPA_PLUGIN_URL . 'assets/js/spa-registration-summary.js',
        ['jquery'],
        '1.1.0',
        true
    );
    
    wp_enqueue_script(
        'spa-infobox',
        SPA_PLUGIN_URL . 'assets/js/spa-infobox.js',
        ['spa-registration'],
        '1.1.0',
        true
    );
    
// ⭐ VYTVOR spaConfig objekt
$field_config = spa_load_field_config();
    
// DEBUG: Skontroluj či funkcia existuje
if (!function_exists('spa_generate_program_cities_map')) {
    error_log('[SPA Bootstrap] CHYBA: spa_generate_program_cities_map() neexistuje!');
    $program_cities = [];
} else {
    $program_cities = spa_generate_program_cities_map();
    error_log('[SPA Bootstrap] programCities count: ' . count($program_cities));
}

wp_localize_script('spa-infobox', 'spaConfig', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'fields' => [
        'spa_city' => $field_config['spa_city'] ?? 'input_1',
        'spa_program' => $field_config['spa_program'] ?? 'input_2',
    ],
    'programCities' => $program_cities,
]);

error_log('[SPA Bootstrap] spaConfig created with fields: ' . print_r($field_config, true));
}

/**
 * AJAX endpoint pre získanie programCities mapy
 */
add_action('wp_ajax_spa_get_program_cities', 'spa_ajax_get_program_cities');
add_action('wp_ajax_nopriv_spa_get_program_cities', 'spa_ajax_get_program_cities');

function spa_ajax_get_program_cities() {
    $program_cities = spa_generate_program_cities_map();
    
    wp_send_json_success([
        'programCities' => $program_cities
    ]);
}