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
     // DEBUG: Dump celého entry
     error_log('[SPA SUBMISSION] Full entry dump: ' . print_r($entry, true));
     error_log('[SPA SUBMISSION] Full POST dump: ' . print_r($_POST, true));
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
    
    $mapping = array();
    
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



    /**
     * Auto-generovanie emailu pre dieťa pred validáciou
     * Hook: gform_pre_validation (priorita 10, pred validáciou GF)
     */
    add_filter('gform_pre_validation', 'spa_autofill_child_email_before_validation');

    function spa_autofill_child_email_before_validation($form) {
        error_log('[SPA REG] gform_pre_validation triggered');
        
        // Načítaj field config
        $field_config = spa_load_field_config();
        
        if (empty($field_config)) {
            return $form;
        }
        
        // Získaj child email field ID
        $child_email_id = str_replace('input_', '', isset($field_config['spa_client_email']) ? $field_config['spa_client_email'] : '');
        
        if (!$child_email_id) {
            return $form;
        }
        
        // ČÍTAJ PARTICIPANT TYPE Z input_34 (spa_resolved_type hidden field)
        $resolved_type = isset($_POST['input_34']) ? $_POST['input_34'] : '';

        error_log('[SPA REG] resolved_type (input_34): ' . $resolved_type);
        
        // Ak nie je child, nerob nič
        if ($resolved_type !== 'child') {
            return $form;
        }
        
        error_log('[SPA REG] Participant type: CHILD');
        
        // Zisti, či je email dieťaťa prázdny
        $child_email = rgpost("input_{$child_email_id}");
        
        if (!empty(trim($child_email))) {
            // Email už je vyplnený, nerob nič
            error_log('[SPA REG] Child email already filled: ' . $child_email);
            return $form;
        }
        
        // Získaj meno a priezvisko
        $first_name = '';
        $last_name = '';
        
        // Hľadaj Name field (zvyčajne má subfields .3 a .6)
        foreach ($form['fields'] as $field) {
            if ($field->type === 'name') {
                $first_name = rgpost("input_{$field->id}_3"); // First Name
                $last_name = rgpost("input_{$field->id}_6");  // Last Name
                break;
            }
        }
        
        error_log('[SPA REG] First name: ' . $first_name . ', Last name: ' . $last_name);
        
        if (empty($first_name) || empty($last_name)) {
            error_log('[SPA REG] Missing name data, cannot generate email');
            return $form;
        }
        
        // Odstráň diakritiku
        $first_name_clean = spa_remove_diacritics_for_email($first_name);
        $last_name_clean = spa_remove_diacritics_for_email($last_name);
        
        // Vygeneruj email
        $generated_email = strtolower($first_name_clean . '.' . $last_name_clean . '@piaseckyacademy.sk');
        
        error_log('[SPA REG] Generated child email: ' . $generated_email);
        
        // Zapíš do $_POST (GF číta odtiaľ pri validácii)
        $_POST["input_{$child_email_id}"] = $generated_email;
        
        // ULOŽIŤ DO TRANSIENT pre field validation hook
        set_transient('spa_generated_child_email_' . $form['id'], $generated_email, 300); // 5 minút
        
        error_log('[SPA REG] Written to POST input_' . $child_email_id);
        
        return $form;
    }


        /**
     * Bypass validácie pre dynamicky načítané programy
     * Hook: gform_field_validation
     */
    add_filter('gform_field_validation', 'spa_bypass_program_validation', 9, 4);

    function spa_bypass_program_validation($result, $value, $form, $field) {
        // Načítaj field config
        $field_config = spa_load_field_config();
        
        if (empty($field_config)) {
            return $result;
        }
        
        $program_field = isset($field_config['spa_program']) ? $field_config['spa_program'] : '';
        $program_id = str_replace('input_', '', $program_field);
        
        // Ak toto nie je program field, nerob nič
        if ($field->id != $program_id) {
            return $result;
        }
        
        error_log('[SPA REG VALIDATION] Processing program field');
        
        // Ak je hodnota vyplnená, považuj za validnú
        // (programy sa načítavajú dynamicky cez AJAX)
        if (!empty($value)) {
            error_log('[SPA REG VALIDATION] Program value: ' . $value . ' - bypassing validation');
            
            $result['is_valid'] = true;
            $result['message'] = '';
        }
        
        return $result;
    }


    /**
     * Bypass validácie pre auto-generovaný child email
     * Hook: gform_field_validation
     */
    add_filter('gform_field_validation', 'spa_bypass_child_email_validation', 10, 4);

    function spa_bypass_child_email_validation($result, $value, $form, $field) {
        // Načítaj field config
        $field_config = spa_load_field_config();
        
        if (empty($field_config)) {
            return $result;
        }
        
        $child_email_field = isset($field_config['spa_client_email']) ? $field_config['spa_client_email'] : '';
        $child_email_id = str_replace('input_', '', $child_email_field);
        
        error_log('[SPA REG VALIDATION] Checking field ' . $field->id . ' against child email field ' . $child_email_id);
        
        // Ak toto nie je child email field, nerob nič
        if ($field->id != $child_email_id) {
            return $result;
        }
        
        // Zisti typ účastníka
        $resolved_type = rgpost('input_34');
        
        if ($resolved_type !== 'child') {
            error_log('[SPA REG VALIDATION] Not a child, using normal validation');
            return $result; // Adult - nechaj normálnu validáciu
        }
        
        error_log('[SPA REG VALIDATION] Processing child email field');
        
        // Pre child - over či máme vygenerovaný email
        $generated_email = get_transient('spa_generated_child_email_' . $form['id']);
        
        if (!$generated_email) {
            error_log('[SPA REG VALIDATION] No generated email in transient');
            return $result;
        }
        
        error_log('[SPA REG VALIDATION] Found generated email: ' . $generated_email);
        
        // KRITICKÉ: Nastav is_valid a vyčisti error message
        $result['is_valid'] = true;
        $result['message'] = '';
        
        // Prepíš hodnotu v $_POST aby GF použila správnu hodnotu
        $_POST[$child_email_field] = $generated_email;
        
        error_log('[SPA REG VALIDATION] Validation bypassed, email set to: ' . $generated_email);
        
        // Vyčisti transient
        delete_transient('spa_generated_child_email_' . $form['id']);
        
        return $result;
    }

    /**
     * Helper: Odstránenie diakritiky pre email
     */
    function spa_remove_diacritics_for_email($string) {
        $diacritics = array(
            'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e',
            'í' => 'i', 'ľ' => 'l', 'ĺ' => 'l', 'ň' => 'n', 'ó' => 'o',
            'ô' => 'o', 'ŕ' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u',
            'ý' => 'y', 'ž' => 'z',
            'Á' => 'A', 'Ä' => 'A', 'Č' => 'C', 'Ď' => 'D', 'É' => 'E',
            'Í' => 'I', 'Ľ' => 'L', 'Ĺ' => 'L', 'Ň' => 'N', 'Ó' => 'O',
            'Ô' => 'O', 'Ŕ' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ú' => 'U',
            'Ý' => 'Y', 'Ž' => 'Z'
        );
        
        $string = strtr($string, $diacritics);
        $string = preg_replace('/[^a-zA-Z0-9]/', '', $string);
        
        return $string;
    }


    /**
 * DEBUG: Log celý validation result
 */
add_filter('gform_validation', 'spa_debug_validation_result', 999);

function spa_debug_validation_result($validation_result) {
    error_log('[SPA DEBUG] Final validation result: ' . ($validation_result['is_valid'] ? 'VALID' : 'INVALID'));
    
    if (!$validation_result['is_valid']) {
        error_log('[SPA DEBUG] Form has validation errors:');
        
        foreach ($validation_result['form']['fields'] as $field) {
            if (!empty($field->failed_validation)) {
                error_log('[SPA DEBUG] Field ' . $field->id . ' (' . $field->label . ') failed: ' . $field->validation_message);
            }
        }
    }
    
    return $validation_result;
}