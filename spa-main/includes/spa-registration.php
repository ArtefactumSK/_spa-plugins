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
    // NOVÝ: Globálny bypass child/guardian polí pre adult flow
    add_filter('gform_field_validation', 'spa_bypass_child_fields_for_adult', 5, 4);
    
    // Bypass dynamických polí (mesto, program)
    add_filter('gform_field_validation', 'spa_bypass_dynamic_fields', 9, 4);
    
    // Auto-generovanie child emailu
    add_filter('gform_pre_validation', 'spa_autofill_child_email_before_validation');
    add_filter('gform_field_validation', 'spa_bypass_child_email_validation', 10, 4);
    
    // Podmienená validácia telefónu
    add_filter('gform_field_validation', 'spa_validate_phone_conditionally', 10, 4);
    
    // Validácia checkbox group súhlasov
  //  add_filter('gform_validation', 'spa_validate_consents', 10);
    
    // Hook po úspešnom submite
    add_action('gform_after_submission', 'spa_gf_after_submission', 10, 2);
    
    // Kompletná validácia registrácie
    add_filter('gform_validation', 'spa_validate_registration_form', 20);
    
    // DEBUG hook
    add_filter('gform_validation', 'spa_debug_validation_result', 999);
}

/**
 * NOVÝ: Globálny bypass child/guardian polí pre adult flow
 * Priorita 5 = pred všetkými ostatnými validáciami
 */
function spa_bypass_child_fields_for_adult($result, $value, $form, $field) {
    // Načítaj resolved_type
    $resolved_type = spa_get_field_value($entry, 'spa_resolved_type');
    
    // Ak ADULT → ignoruj všetky child/guardian polia
    if ($resolved_type === 'adult') {
        // Zoznam child/guardian field IDs (podľa logu)
        $child_fields = [
            6,   // Child Name (field 6)
            7,   // Child Birth Date
            8,   // Birth Number (Rodné číslo)
            12,  // Guardian Relation
            13,  // Parent Phone
            18,  // Guardian Name (podľa logu: "Meno, Priezvisko")
            42,  // Neznáme child pole
            // Pridaj ďalšie podľa potreby
        ];
        
        if (in_array($field->id, $child_fields)) {
            error_log('[SPA VALIDATION] Bypassing field ' . $field->id . ' (adult flow)');
            $result['is_valid'] = true;
            $result['message'] = '';
        }
    }
    
    // Ak CHILD → ignoruj adult-only polia
    if ($resolved_type === 'child') {
        $adult_fields = [
            18, // Adult Name (ak je to adult-specific)
            19, // Client Phone (adult)
        ];
        
        // Poznámka: field 18 je v logu uvedené ako "Meno, Priezvisko"
        // Ak je to guardian name, NEVYNECHÁVAJ ho pri child flow
        // Úprava: odstránil som 18 z adult_fields, lebo patrí guardian
    }
    
    return $result;
}

/**
 * Bypass validácie pre dynamicky načítané polia
 */
function spa_bypass_dynamic_fields($result, $value, $form, $field) {
    // Field 2 = Program (dynamický)
    if ($field->id == 2 && !empty($value)) {
        error_log('[SPA VALIDATION] Bypassing program field validation');
        $result['is_valid'] = true;
        $result['message'] = '';
    }
    
    return $result;
}

/**
 * Auto-generovanie emailu pre dieťa
 */
function spa_autofill_child_email_before_validation($form) {
    error_log('[SPA REG] gform_pre_validation triggered');
    
    $field_config = spa_load_field_config();
    if (empty($field_config)) {
        return $form;
    }
    
    // Field 34 = spa_resolved_type (hidden)
    $resolved_type = spa_get_field_value($entry, 'spa_resolved_type');
    error_log('[SPA REG] resolved_type: ' . $resolved_type);
    
    if ($resolved_type !== 'child') {
        return $form;
    }
    
    // Field 16 = required email (child)
    $child_email = spa_get_field_value($entry, 'spa_client_email');
    
    if (!empty(trim($child_email))) {
        error_log('[SPA REG] Child email already filled');
        return $form;
    }
    
    // Field 6.3 = First Name, 6.6 = Last Name
    $first_name = spa_get_field_value($entry, 'spa_member_name_first');
    $last_name = spa_get_field_value($entry, 'spa_member_name_last');
    
    error_log('[SPA REG] Name: ' . $first_name . ' ' . $last_name);
    
    if (empty($first_name) || empty($last_name)) {
        return $form;
    }
    
    // Generuj email
    $first_clean = spa_remove_diacritics_for_email($first_name);
    $last_clean = spa_remove_diacritics_for_email($last_name);
    $generated_email = strtolower($first_clean . '.' . $last_clean . '@piaseckyacademy.sk');
    
    error_log('[SPA REG] Generated email: ' . $generated_email);
    
    // Zapíš do POST a transient
    $_POST[spa_get_field_value($entry, 'spa_client_email')] = $generated_email;
    set_transient('spa_generated_child_email_' . $form['id'], $generated_email, 300);
    
    return $form;
}

/**
 * Bypass validácie pre auto-generovaný child email
 */
function spa_bypass_child_email_validation($result, $value, $form, $field) {
    // Field 16 = spa_client_email_required
    if ($field->id != 16) {
        return $result;
    }
    
    $resolved_type = spa_get_field_value($entry, 'spa_resolved_type');
    
    if ($resolved_type !== 'child') {
        return $result;
    }
    
    $generated_email = get_transient('spa_generated_child_email_' . $form['id']);
    
    if ($generated_email) {
        $result['is_valid'] = true;
        $result['message'] = '';
        $_POST[spa_get_field_value($entry, 'spa_client_email')] = $generated_email;
        
        error_log('[SPA VALIDATION] Bypassed email validation: ' . $generated_email);
        delete_transient('spa_generated_child_email_' . $form['id']);
    }
    
    return $result;
}

/**
 * Podmienená validácia telefónu
 * Child → validuj field 13 (parent phone)
 * Adult → validuj field 19 (client phone)
 */
function spa_validate_phone_conditionally($result, $value, $form, $field) {
    $resolved_type = spa_get_field_value($entry, 'spa_resolved_type');
    
    // Field 13 = spa_parent_phone (child)
    if ($field->id == 13) {
        if ($resolved_type === 'child' && empty($value)) {
            $result['is_valid'] = false;
            $result['message'] = 'Telefón zákonného zástupcu je povinný.';
            error_log('[SPA VALIDATION] Parent phone missing (child registration)');
        } else {
            error_log('[SPA VALIDATION] Parent phone OK: ' . $value);
        }
    }
    
    // Field 19 = spa_client_phone (adult)
    if ($field->id == 19) {
        if ($resolved_type === 'adult' && empty($value)) {
            $result['is_valid'] = false;
            $result['message'] = 'Telefón účastníka je povinný.';
            error_log('[SPA VALIDATION] Client phone missing (adult registration)');
        } else {
            error_log('[SPA VALIDATION] Client phone OK: ' . $value);
        }
    }
    
    return $result;
}

/**
 * Spracovanie po úspešnom submite
 */
function spa_gf_after_submission($entry, $form) {
    error_log('[SPA SUBMISSION] Entry ID: ' . $entry['id']);
    
    $resolved_type = spa_get_field_value($entry, 'spa_resolved_type');
    
    error_log('[SPA SUBMISSION] Program: ' . rgar($entry, '2'));
    error_log('[SPA SUBMISSION] Type: ' . $resolved_type);
    
    // Načítaj údaje z formulára
    $first_name = spa_get_field_value($entry, 'spa_member_name_first');
    $last_name = spa_get_field_value($entry, 'spa_member_name_last');
    $birthdate = spa_get_field_value($entry, 'spa_member_birthdate');
    $health_notes = spa_get_field_value($entry, 'spa_member_health_restrictions');
    $address_street = spa_get_field_value($entry, 'spa_client_address');
    $address_city = spa_get_field_value($entry, 'spa_client_address_city');
    $address_zip = spa_get_field_value($entry, 'spa_client_address_zip');
    
    // CHILD flow
    if ($resolved_type === 'child') {
        $child_email = spa_get_field_value($entry, 'spa_client_email');
        $parent_email = spa_get_field_value($entry, 'spa_parent_email');
        $parent_phone = spa_get_field_value($entry, 'spa_parent_phone');
        $parent_first_name = spa_get_field_value($entry, 'spa_guardian_name_first');
        $parent_last_name = spa_get_field_value($entry, 'spa_guardian_name_last');
        $birth_number = spa_get_field_value($entry, 'spa_member_birthnumber');
        
        error_log('[SPA SUBMISSION] Child email: ' . $child_email);
        error_log('[SPA SUBMISSION] Parent email: ' . $parent_email);
        error_log('[SPA SUBMISSION] Parent phone: ' . $parent_phone);
        
        // 1. Vytvor/získaj parent usera
        $parent_data = [
            'user_email' => $parent_email,
            'first_name' => $parent_first_name,
            'last_name' => $parent_last_name,
            'role' => 'spa_parent',
        ];
        
        $parent_meta = [
            'phone' => $parent_phone,
            'address_street' => $address_street,
            'address_city' => $address_city,
            'address_zip' => $address_zip,
        ];
        
        $parent_user_id = spa_get_or_create_parent_user($parent_data, $parent_meta);
        
        if (!$parent_user_id) {
            error_log('[SPA ERROR] Failed to create parent user');
            return;
        }
        
        // 2. Vytvor/získaj child usera
        $child_data = [
            'user_email' => $child_email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'spa_child',
        ];
        
        // Konverzia dátumu z GF formátu (d.m.Y) na ISO (Y-m-d)
        $birthdate_iso = spa_convert_date_to_iso($birthdate);
        
        $child_meta = [
            'birthdate' => $birthdate_iso,
            'birth_number' => $birth_number,
            'health_notes' => $health_notes,
        ];
        
        $child_user_id = spa_get_or_create_child_user($child_data, $parent_user_id, $child_meta);
        
        if (!$child_user_id) {
            error_log('[SPA ERROR] Failed to create child user');
            return;
        }
        
        // 3. Vygeneruj VS pre dieťa (ak ešte nemá)
        spa_generate_and_store_vs($child_user_id);
        
        error_log('[SPA SUBMISSION] Child user_id: ' . $child_user_id);
        error_log('[SPA SUBMISSION] Parent user_id: ' . $parent_user_id);
    }
    
    // ADULT flow
    if ($resolved_type === 'adult') {
        $adult_email = spa_get_field_value($entry, 'spa_client_email');
        $adult_phone = spa_get_field_value($entry, 'spa_client_phone');
        
        error_log('[SPA SUBMISSION] Adult email: ' . $adult_email);
        error_log('[SPA SUBMISSION] Adult phone: ' . $adult_phone);
        
        // 1. Vytvor/získaj adult usera
        $adult_data = [
            'user_email' => $adult_email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'spa_client',
        ];
        
        // Konverzia dátumu z GF formátu (d.m.Y) na ISO (Y-m-d)
        $birthdate_iso = spa_convert_date_to_iso($birthdate);
        
        $adult_meta = [
            'phone' => $adult_phone,
            'address_street' => $address_street,
            'address_city' => $address_city,
            'address_zip' => $address_zip,
            'birthdate' => $birthdate_iso,
            'health_notes' => $health_notes,
        ];
        
        $adult_user_id = spa_get_or_create_adult_user($adult_data, $adult_meta);
        
        if (!$adult_user_id) {
            error_log('[SPA ERROR] Failed to create adult user');
            return;
        }
        
        // 2. Vygeneruj VS pre dospelého (ak ešte nemá)
        spa_generate_and_store_vs($adult_user_id);
        
        error_log('[SPA SUBMISSION] Adult user_id: ' . $adult_user_id);
    }
}

/**
 * DEBUG validácie
 */
function spa_debug_validation_result($validation_result) {
    error_log('[SPA DEBUG] Final validation: ' . ($validation_result['is_valid'] ? 'VALID' : 'INVALID'));
    
    if (!$validation_result['is_valid']) {
        foreach ($validation_result['form']['fields'] as $field) {
            if (!empty($field->failed_validation)) {
                error_log('[SPA DEBUG] Field ' . $field->id . ' failed: ' . $field->validation_message);
            }
        }
    }
    
    return $validation_result;
}

/**
 * Helper: Konverzia dátumu z GF formátu (d.m.Y) na ISO (Y-m-d)
 */
function spa_convert_date_to_iso($date_string) {
    if (empty($date_string)) {
        return '';
    }
    
    // GF vracia dátum v formáte d.m.Y (napr. 15.03.2010)
    $date = DateTime::createFromFormat('d.m.Y', $date_string);
    
    if (!$date) {
        error_log('[SPA ERROR] Invalid date format: ' . $date_string);
        return '';
    }
    
    return $date->format('Y-m-d');
}

/**
 * Helper: Odstránenie diakritiky
 */
function spa_remove_diacritics_for_email($string) {
    $diacritics = [
        'á'=>'a','ä'=>'a','č'=>'c','ď'=>'d','é'=>'e','í'=>'i',
        'ľ'=>'l','ĺ'=>'l','ň'=>'n','ó'=>'o','ô'=>'o','ŕ'=>'r',
        'š'=>'s','ť'=>'t','ú'=>'u','ý'=>'y','ž'=>'z',
        'Á'=>'A','Ä'=>'A','Č'=>'C','Ď'=>'D','É'=>'E','Í'=>'I',
        'Ľ'=>'L','Ĺ'=>'L','Ň'=>'N','Ó'=>'O','Ô'=>'O','Ŕ'=>'R',
        'Š'=>'S','Ť'=>'T','Ú'=>'U','Ý'=>'Y','Ž'=>'Z'
    ];
    
    $string = strtr($string, $diacritics);
    $string = preg_replace('/[^a-zA-Z0-9]/', '', $string);
    
    return $string;
}


/**
 * Kompletná validácia registračného formulára
 * CHILD: meno, adresa, dátum narodenia, rodné číslo, vek v tolerancii, zákonný zástupca, súhlas 42
 * ADULT: meno, adresa, email, telefón, súhlasy 35
 */
function spa_validate_registration_form($validation_result) {
    $form = $validation_result['form'];
        
    // KRITICKÉ: Zisti resolved_type z $_POST (nie z $entry - ten tu ešte neexistuje)
    $resolved_type = rgpost('spa_resolved_type'); // input_34 = spa_resolved_type

    if (empty($resolved_type)) {
        error_log('[SPA VALIDATION] No spa_resolved_type – skipping validation');
        return $validation_result;
    }    
    
    error_log('[SPA VALIDATION] Resolved type: ' . $resolved_type);
    
    
    // === SPOLOČNÉ VALIDÁCIE (CHILD aj ADULT) ===
    
    // Meno účastníka
    $first_name = spa_get_field_value($entry, 'spa_member_name_first'); // Meno
    $last_name  = spa_get_field_value($entry, 'spa_member_name_last'); // Priezvisko    
    
    if (empty(trim($first_name)) || empty(trim($last_name))) {
        foreach ($form['fields'] as &$field) {
            if ($field->id == 6) {
                $field->failed_validation = true;
                $field->validation_message = 'Meno a priezvisko účastníka sú povinné.';
                $validation_result['is_valid'] = false;
            }
        }
    }
    
    // Adresa - ulica, mesto, PSČ
    $address_street = spa_get_field_value($entry, 'spa_client_address'); // Ulica
    $address_city   = spa_get_field_value($entry, 'spa_client_address_city'); // Mesto
    $address_zip    = spa_get_field_value($entry, 'spa_client_address_zip'); // PSČ

    
    $address_errors = [];
    if (empty(trim($address_street))) $address_errors[] = 'ulica';
    if (empty(trim($address_city))) $address_errors[] = 'mesto';
    if (empty(trim($address_zip))) $address_errors[] = 'PSČ';
    
    if (!empty($address_errors)) {
        foreach ($form['fields'] as &$field) {
            if ($field->id == 17) {
                $field->failed_validation = true;
                $field->validation_message = 'Adresa je povinná: chýba ' . implode(', ', $address_errors) . '.';
                $validation_result['is_valid'] = false;
            }
        }
    }
    
    // === CHILD VALIDÁCIE ===
    if ($resolved_type === 'child') {
        
        // Dátum narodenia
        $birthdate = spa_get_field_value($entry, 'spa_member_birthdate');
        if (empty(trim($birthdate))) {
            foreach ($form['fields'] as &$field) {
                if ($field->id == 7) {
                    $field->failed_validation = true;
                    $field->validation_message = 'Dátum narodenia je povinný.';
                    $validation_result['is_valid'] = false;
                }
            }
        }
        
        // Rodné číslo
        $birth_number = spa_get_field_value($entry, 'spa_member_birthnumber');
        if (empty(trim($birth_number))) {
            foreach ($form['fields'] as &$field) {
                if ($field->id == 8) {
                    $field->failed_validation = true;
                    $field->validation_message = 'Rodné číslo je povinné.';
                    $validation_result['is_valid'] = false;
                }
            }
        }
        
        // Vek v tolerancii programu
        if (!empty($birthdate)) {
            $program_id = spa_get_field_value($entry, 'spa_program');
            
            if (!empty($program_id) && is_numeric($program_id)) {
                $age_min = get_post_meta($program_id, 'spa_age_min', true);
                $age_max = get_post_meta($program_id, 'spa_age_max', true);
                
                // Vypočítaj vek
                $birth_date = DateTime::createFromFormat('d.m.Y', $birthdate);
                if ($birth_date) {
                    $today = new DateTime();
                    $age = $birth_date->diff($today)->y;
                    
                    $age_min_float = floatval($age_min);
                    $age_max_float = floatval($age_max);
                    
                    $age_error = false;
                    
                    if (!empty($age_min) && !empty($age_max)) {
                        if ($age < $age_min_float || $age > $age_max_float) {
                            $age_error = true;
                        }
                    } elseif (!empty($age_min) && $age < $age_min_float) {
                        $age_error = true;
                    }
                    
                    if ($age_error) {
                        foreach ($form['fields'] as &$field) {
                            if ($field->id == 7) {
                                $field->failed_validation = true;
                                $field->validation_message = 'Vek účastníka (' . $age . ' rokov) nezodpovedá vekovej kategórii programu (' . $age_min . '-' . $age_max . ' r.).';
                                $validation_result['is_valid'] = false;
                            }
                        }
                    }
                }
            }
        }
        
        // Zákonný zástupca - meno
        $guardian_first = spa_get_field_value($entry, 'spa_guardian_name_first');
        $guardian_last = spa_get_field_value($entry, 'spa_guardian_name_last');
        
        if (empty(trim($guardian_first)) || empty(trim($guardian_last))) {
            foreach ($form['fields'] as &$field) {
                if ($field->id == 18) {
                    $field->failed_validation = true;
                    $field->validation_message = 'Meno a priezvisko zákonného zástupcu sú povinné.';
                    $validation_result['is_valid'] = false;
                }
            }
        }
        
        // Zákonný zástupca - email
        $guardian_email = spa_get_field_value($entry, 'spa_parent_email');
        if (empty(trim($guardian_email))) {
            foreach ($form['fields'] as &$field) {
                if ($field->id == 12) {
                    $field->failed_validation = true;
                    $field->validation_message = 'E-mail zákonného zástupcu je povinný.';
                    $validation_result['is_valid'] = false;
                }
            }
        }
        
        // Zákonný zástupca - telefón
        $guardian_phone = spa_get_field_value($entry, 'spa_parent_phone');
        if (empty(trim($guardian_phone))) {
            foreach ($form['fields'] as &$field) {
                if ($field->id == 13) {
                    $field->failed_validation = true;
                    $field->validation_message = 'Telefón zákonného zástupcu je povinný.';
                    $validation_result['is_valid'] = false;
                }
            }
        }
        
        // Súhlas zákonného zástupcu (checkbox 42)
        $guardian_consent = spa_get_field_value($entry, 'spa_consent_guardian'); // Súhlas zákonného zástupcu
        if (empty($guardian_consent)) {
            foreach ($form['fields'] as &$field) {
                if ($field->id == 42) {
                    $field->failed_validation = true;
                    $field->validation_message = 'Potvrdenie zákonného zástupcu je povinné.';
                    $validation_result['is_valid'] = false;
                }
            }
        }
    }
    
    // === ADULT VALIDÁCIE ===
    if ($resolved_type === 'adult') {
        
        // Email účastníka
        $adult_email = spa_get_field_value($entry, 'spa_client_email');
        if (empty(trim($adult_email))) {
            foreach ($form['fields'] as &$field) {
                if ($field->id == 16) {
                    $field->failed_validation = true;
                    $field->validation_message = 'E-mail účastníka je povinný.';
                    $validation_result['is_valid'] = false;
                }
            }
        }
        
        // Telefón účastníka
        $adult_phone = spa_get_field_value($entry, 'spa_client_phone');
        if (empty(trim($adult_phone))) {
            foreach ($form['fields'] as &$field) {
                if ($field->id == 19) {
                    $field->failed_validation = true;
                    $field->validation_message = 'Telefón účastníka je povinný.';
                    $validation_result['is_valid'] = false;
                }
            }
        }
    }
    
    // === GDPR SÚHLASY (OBA TYPY) ===
    // Field 35 má 4 checkboxy - všetky povinné
    $consent_1 = spa_get_field_value($entry, 'spa_consent_gdpr'); // GDPR
    $consent_2 = spa_get_field_value($entry, 'spa_consent_health'); // Zdravotné údaje
    $consent_3 = spa_get_field_value($entry, 'spa_consent_statutes'); // Stanovy
    $consent_4 = spa_get_field_value($entry, 'spa_consent_terms'); // Podmienky
    
    if (empty($consent_1) || empty($consent_2) || empty($consent_3) || empty($consent_4)) {
        foreach ($form['fields'] as &$field) {
            if ($field->id == 35) {
                $field->failed_validation = true;
                $field->validation_message = 'Všetky súhlasy sú povinné.';
                $validation_result['is_valid'] = false;
            }
        }
    }
    
    $validation_result['form'] = $form;
    
    return $validation_result;
}

add_filter('gform_validation', 'spa_validate_registration_form', 20);