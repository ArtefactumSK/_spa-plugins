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

// POZNÁMKA: SPA_VERSION sa NENASTAVUJE - téma má prioritu

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

    // Inicializácia user managementu
    require_once SPA_PLUGIN_DIR . 'includes/spa-user-create.php';
    require_once SPA_PLUGIN_DIR . 'includes/spa-user-management.php';
    spa_user_management_init();
}

add_action('plugins_loaded', 'spa_init', 5); // Priorita 5 = skoršie ako téma

/**
 * Enqueue scripts - PRIAMO cez wp_enqueue_scripts
 */
add_action('wp_enqueue_scripts', 'spa_enqueue_frontend_scripts', 20);

function spa_enqueue_frontend_scripts() {
    // Len pre frontend
    if (is_admin()) {
        return;
    }
    
    error_log('[SPA Enqueue] === SCRIPTS START ===');
    
    $field_config = spa_load_field_config();
    
    // 1. Enqueue JS súbory
    wp_enqueue_script(
        'spa-registration',
        SPA_PLUGIN_URL . 'assets/js/spa-registration-summary.js',
        ['jquery'],
        SPA_PLUGIN_VERSION,
        true
    );
    
    wp_enqueue_script(
        'spa-infobox',
        SPA_PLUGIN_URL . 'assets/js/spa-infobox.js',
        ['spa-registration'],
        '1.0.4',
        true
    );
    
    // 2. Generuj mapu
    $program_cities = spa_generate_program_cities_map();
    
    error_log('[SPA Enqueue] Program cities count: ' . count($program_cities));
    
    // 3. Localize HNEĎ po enqueue
    wp_localize_script('spa-registration', 'spaConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'fields' => [
            'spa_city' => $field_config['spa_city'] ?? '',
            'spa_program' => $field_config['spa_program'] ?? '',
            'spa_registration_type' => $field_config['spa_registration_type'] ?? '',
            'spa_resolved_type' => $field_config['spa_resolved_type'] ?? '',
            'spa_client_email' => $field_config['spa_client_email'] ?? '',
        ],
        'programCities' => $program_cities,
        'nonce' => wp_create_nonce('spa_ajax_nonce'),
    ]);
    // ⭐ FORCE inline programCities ako backup
    $program_cities_json = json_encode($program_cities, JSON_UNESCAPED_UNICODE);
    
    wp_add_inline_script('spa-registration', "
        console.log('[SPA Inline] Forcing programCities...');
        if (typeof spaConfig === 'undefined') {
            window.spaConfig = {
                ajaxUrl: '" . admin_url('admin-ajax.php') . "',
                fields: {
                    spa_city: '" . ($field_config['spa_city'] ?? 'input_1') . "',
                    spa_program: '" . ($field_config['spa_program'] ?? 'input_2') . "',
                    spa_registration_type: '" . ($field_config['spa_registration_type'] ?? 'input_4') . "',
                    spa_resolved_type: '" . ($field_config['spa_resolved_type'] ?? 'input_34') . "',
                    spa_client_email: '" . ($field_config['spa_client_email'] ?? 'input_15') . "'
                },
                programCities: {$program_cities_json},
                nonce: '" . wp_create_nonce('spa_ajax_nonce') . "'
            };
        } else {
            spaConfig.programCities = {$program_cities_json};
        }
        console.log('[SPA Inline] spaConfig created:', spaConfig);
    ", 'before');
    
    error_log('[SPA Enqueue] === SCRIPTS DONE ===');
}