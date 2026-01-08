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
    // Programové nastavenie radio button pred renderom
    add_filter('gform_pre_render', 'spa_gf_set_registration_type', 10, 3);
    
    // Dynamická conditional logic pre sekcie a polia
    add_filter('gform_pre_render', 'spa_gf_dynamic_conditional_logic', 20, 3);
    
    // Validácia formulára pred submissionom
    add_filter('gform_validation', 'spa_gf_validate_registration');
    
    // Hook po úspešnom submite
    add_action('gform_after_submission', 'spa_gf_after_submission', 10, 2);
}

/**
 * Nastavenie default hodnoty radio spa_registration_type podľa JS
 */
function spa_gf_set_registration_type($form, $is_ajax, $field_values) {
    $config = spa_load_field_config();
    $resolved_type_input = $config['spa_resolved_type'] ?? null;
    $registration_type_input = $config['spa_registration_type'] ?? null;
    
    if (!$resolved_type_input || !$registration_type_input) {
        return $form;
    }
    
    // Získaj field ID z input_XX
    preg_match('/input_(\d+)/', $resolved_type_input, $matches);
    $resolved_field_id = isset($matches[1]) ? intval($matches[1]) : null;
    
    preg_match('/input_(\d+)/', $registration_type_input, $matches);
    $registration_field_id = isset($matches[1]) ? intval($matches[1]) : null;
    
    if (!$resolved_field_id || !$registration_field_id) {
        return $form;
    }
    
    // Prečítaj hodnotu z hidden field (POST alebo field_values)
    $resolved_type = '';
    
    if (isset($_POST["input_{$resolved_field_id}"])) {
        $resolved_type = sanitize_text_field($_POST["input_{$resolved_field_id}"]);
    } elseif (isset($field_values['spa_resolved_type'])) {
        $resolved_type = sanitize_text_field($field_values['spa_resolved_type']);
    }
    
    // Ak je resolved_type prázdny, skonči
    if (empty($resolved_type) || !in_array($resolved_type, ['child', 'adult'])) {
        return $form;
    }
    
    // Nastav default hodnotu radio button
    foreach ($form['fields'] as &$field) {
        if ($field->id == $registration_field_id && $field->type == 'radio') {
            // Nastav default choice
            foreach ($field->choices as &$choice) {
                if ($choice['value'] == $resolved_type) {
                    $choice['isSelected'] = true;
                } else {
                    $choice['isSelected'] = false;
                }
            }
            
            // Nastav default value
            $field->defaultValue = $resolved_type;
            
            break;
        }
    }
    
    return $form;
}

/**
 * Dynamická conditional logic pre sekcie a required polia
 */
function spa_gf_dynamic_conditional_logic($form, $is_ajax, $field_values) {
    $config = spa_load_field_config();
    $registration_type_input = $config['spa_registration_type'] ?? null;
    $client_email_input = $config['spa_client_email'] ?? null;
    
    if (!$registration_type_input) {
        return $form;
    }
    
    // Získaj field ID
    preg_match('/input_(\d+)/', $registration_type_input, $matches);
    $registration_field_id = isset($matches[1]) ? intval($matches[1]) : null;
    
    if (!$registration_field_id) {
        return $form;
    }
    
    // Zisti aktuálnu hodnotu radio
    $current_type = '';
    if (isset($_POST["input_{$registration_field_id}"])) {
        $current_type = sanitize_text_field($_POST["input_{$registration_field_id}"]);
    }
    
    // Ak hodnota ešte nie je nastavená, skonči
    if (empty($current_type)) {
        return $form;
    }
    
    // Aplikuj logiku pre každé pole
    foreach ($form['fields'] as &$field) {
        
        // SEKCIA RODIČA: identifikuj podľa label
        if ($field->type == 'section' && 
            (strpos(strtolower($field->label), 'rodič') !== false || 
             strpos(strtolower($field->label), 'zákonný zástupca') !== false)) {
            
            // Nastav conditional logic
            $field->conditionalLogic = [
                'actionType' => 'show',
                'logicType' => 'all',
                'rules' => [
                    [
                        'fieldId' => $registration_field_id,
                        'operator' => 'is',
                        'value' => 'child'
                    ]
                ]
            ];
        }
        
        // EMAIL ÚČASTNÍKA: required len pre adult
        if ($client_email_input) {
            preg_match('/input_(\d+)/', $client_email_input, $matches);
            $email_field_id = isset($matches[1]) ? intval($matches[1]) : null;
            
            if ($field->id == $email_field_id) {
                $field->isRequired = ($current_type === 'adult');
            }
        }
        
        // PARENT EMAIL / PHONE: required len pre child
        // Identifikuj podľa cssClass alebo adminLabel
        if (isset($field->cssClass) && 
            (strpos($field->cssClass, 'spa-parent-email') !== false || 
             strpos($field->cssClass, 'spa-parent-phone') !== false)) {
            
            $field->isRequired = ($current_type === 'child');
            
            // Nastav conditional logic
            $field->conditionalLogic = [
                'actionType' => 'show',
                'logicType' => 'all',
                'rules' => [
                    [
                        'fieldId' => $registration_field_id,
                        'operator' => 'is',
                        'value' => 'child'
                    ]
                ]
            ];
        }
    }
    
    return $form;
}

/**
 * Gravity Forms validácia
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
 */
function spa_get_error_field_mapping() {
    $config = spa_load_field_config();
    
    $mapping = [];
    
    foreach ($config as $logical_name => $input_id) {
        preg_match('/input_(\d+)/', $input_id, $matches);
        if (isset($matches[1])) {
            $mapping[$logical_name] = intval($matches[1]);
        }
    }
    
    return $mapping;
}