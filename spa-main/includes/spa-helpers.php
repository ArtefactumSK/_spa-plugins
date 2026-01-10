<?php
/**
 * SPA System MAIN Helpers
 * Helper funkcie pre validáciu a spracovanie dát
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validácia emailovej adresy
 */
function spa_validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validácia telefónneho čísla (základná)
 * Akceptuje formáty: +421, 0, medzery, pomlčky
 */
function spa_validate_phone($phone) {
    $cleaned = preg_replace('/[\s\-\(\)]/', '', $phone);
    return preg_match('/^(\+421|0)[0-9]{9}$/', $cleaned);
}

/**
 * Sanitizácia telefónneho čísla
 */
function spa_sanitize_phone($phone) {
    return preg_replace('/[^0-9+]/', '', $phone);
}

/**
 * Validácia adresy (GF Address field)
 * Overuje, či sú vyplnené povinné časti adresy
 */
function spa_validate_address($address) {
    if (!is_array($address)) {
        return false;
    }
    
    $required_fields = ['street', 'city', 'zip'];
    
    foreach ($required_fields as $field) {
        if (empty($address[$field])) {
            return false;
        }
    }
    
    return true;
}

    /**
     * Získanie hodnoty z GF entry pomocou spa-config mapingu
     */
    function spa_get_field_value($entry, $logical_name) {
        $config = spa_load_field_config();
        
        if (!isset($config[$logical_name])) {
            error_log('[SPA HELPER] Logical name not found in config: ' . $logical_name);
            return null;
        }
        
        $field_id_with_input = $config[$logical_name]; // napr. "input_2"
        
        // Extrahuj číselné ID (input_2 → 2, input_6_3 → 6.3)
        $field_id = str_replace('input_', '', $field_id_with_input);
        
        // Pre subfields (napr. 6_3) zmeň _ na . (GF používa bodku)
        $field_id = str_replace('_', '.', $field_id);
        
        $value = rgar($entry, $field_id);
        
        error_log('[SPA HELPER] Getting field: ' . $logical_name . ' (ID: ' . $field_id . ') = ' . print_r($value, true));
        
        return $value;
    }

/**
 * Kontrola, či je checkbox/consent zaškrtnutý
 */
function spa_is_consent_checked($entry, $consent_name) {
    $value = spa_get_field_value($entry, $consent_name);
    return !empty($value);
}

/**
 * Loguje chybu do WP debug.log (ak je WP_DEBUG aktívny)
 */
function spa_log($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        $log_message = '[SPA] ' . $message;
        if ($data !== null) {
            $log_message .= ' | Data: ' . print_r($data, true);
        }
        error_log($log_message);
    }
}