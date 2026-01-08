<?php
/**
 * SPA Registration Module
 * Gravity Forms integrácia a validácia registrácií
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializácia registračného modulu
 */
function spa_registration_init() {
    // Hook na validáciu formulára pred submissionom
    add_filter('gform_validation', 'spa_gf_validate_registration');
    
    // Hook po úspešnom submite
    add_action('gform_after_submission', 'spa_gf_after_submission', 10, 2);
}

/**
 * Gravity Forms validácia
 * Pripojené na gform_validation filter
 */
function spa_gf_validate_registration($validation_result) {
    $form = $validation_result['form'];
    $entry = GFFormsModel::get_current_lead();
    
    // Vytvorenie registračného objektu
    $registration = spa_create_registration_object($entry);
    
    // Validácia registrácie
    $validation = spa_validate_registration($registration);
    
    // Ak validácia zlyhala, pridaj chyby do GF
    if (!$validation['valid']) {
        $validation_result['is_valid'] = false;
        
        foreach ($form['fields'] as &$field) {
            $field_id = $field->id;
            
            // Mapovanie logických názvov na field ID
            $error_mapping = spa_get_error_field_mapping();
            
            foreach ($validation['errors'] as $logical_name => $error_message) {
                if (isset($error_mapping[$logical_name]) && $error_mapping[$logical_name] == $field_id) {
                    $field->failed_validation = true;
                    $field->validation_message = $error_message;
                }
            }
        }
        
        $validation_result['form'] = $form;
    }
    
    return $validation_result;
}

/**
 * Spracovanie po úspešnom submite
 */
function spa_gf_after_submission($entry, $form) {
    // Vytvorenie registračného objektu
    $registration = spa_create_registration_object($entry);
    
    // Finálna validácia
    $validation = spa_validate_registration($registration);
    
    if (!$validation['valid']) {
        spa_log('Registration validation failed after submission', $validation['errors']);
        return;
    }
    
    // Spracovanie registrácie
    $result = spa_process_registration($registration);
    
    if ($result['success']) {
        spa_log('Registration processed successfully', $result['data']);
    } else {
        spa_log('Registration processing failed', $result);
    }
}

/**
 * Mapovanie logických názvov chýb na GF field ID
 * Používa spa-config pre získanie správnych field ID
 */
function spa_get_error_field_mapping() {
    $config = spa_load_field_config();
    
    $mapping = [];
    
    foreach ($config as $logical_name => $input_id) {
        // Extrahuj číslo field ID z input_XX formátu
        // input_12 -> 12, input_30.1 -> 30
        preg_match('/input_(\d+)/', $input_id, $matches);
        if (isset($matches[1])) {
            $mapping[$logical_name] = intval($matches[1]);
        }
    }
    
    return $mapping;
}