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

/**
 * DISABLE Gravity Forms conditional logic pre scope fields
 * CRITICAL: GF conditional logic prepisuje náš orchestrátor
 */
add_filter('gform_pre_render', 'spa_disable_conditional_logic_for_scope_fields', 999);

function spa_disable_conditional_logic_for_scope_fields($form) {
    if (!$form || empty($form['fields'])) {
        return $form;
    }
    
    // Load field mapping
    $fields_config = include(SPA_PLUGIN_DIR . 'spa-config/fields.php');
    $fields_json_path = SPA_PLUGIN_DIR . 'spa-config/fields.json';
    
    if (file_exists($fields_json_path)) {
        $json_content = file_get_contents($fields_json_path);
        $fields_registry = json_decode($json_content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $fields_config = array_merge($fields_config, $fields_registry);
        }
    }
    
    // Scope field names to disable
    $scope_field_names = [
        $fields_config['spa_guardian_name_first'] ?? 'input_18.3',
        $fields_config['spa_guardian_name_last'] ?? 'input_18.6',
        $fields_config['spa_parent_email'] ?? 'input_12',
        $fields_config['spa_parent_phone'] ?? 'input_13',
        $fields_config['spa_client_email'] ?? 'input_15',
        $fields_config['spa_consent_guardian'] ?? 'input_42.1',
        $fields_config['spa_member_birthnumber'] ?? 'input_8',
        $fields_config['spa_client_email_required'] ?? 'input_16',
    ];
    
    // Extract field IDs from input names (e.g., "input_18.3" → 18)
    $scope_field_ids = array_map(function($name) {
        preg_match('/input_(\d+)/', $name, $matches);
        return isset($matches[1]) ? (int)$matches[1] : null;
    }, $scope_field_names);
    
    $scope_field_ids = array_filter($scope_field_ids);
    
    // Disable conditional logic for these fields
    foreach ($form['fields'] as &$field) {
        if (in_array($field->id, $scope_field_ids, true)) {
            $field->conditionalLogic = null;
            error_log('[SPA] Disabled conditional logic for field ID: ' . $field->id);
        }
    }
    
    return $form;
}

define('SPA_PLUGIN_FILE', __FILE__);
define('SPA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Načítanie bootstrap súboru
require_once SPA_PLUGIN_DIR . 'includes/bootstrap.php';