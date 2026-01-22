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
    
    // ⭐ NOVÝ: Hlavná server-side validácia
    add_filter('gform_validation', 'spa_validate_registration', 10);
    
    // ⭐ NOVÝ: Označenie validation errorov JS flagom
    add_filter('gform_validation_message', 'spa_mark_validation_errors', 10, 2);
    
    // ⭐ NOVÝ: Validácia povinných súhlasov
    add_filter('gform_validation', 'spa_validate_required_consents', 10);
    
    // ⭐ NOVÝ: Označenie validation errorov JS flagom
    add_filter('gform_validation_message', 'spa_mark_validation_errors', 10, 2);
    
    // Hook po úspešnom submite
    add_action('gform_after_submission', 'spa_gf_after_submission', 10, 2);
    
    // DEBUG hook
    add_filter('gform_validation', 'spa_debug_validation_result', 999);
}

/**
 * NOVÝ: Globálny bypass child/guardian polí pre adult flow
 * Priorita 5 = pred všetkými ostatnými validáciami
 */
function spa_bypass_child_fields_for_adult($result, $value, $form, $field) {
    // Načítaj resolved_type
    $resolved_type = rgpost('input_34');
    
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
    $resolved_type = rgpost('input_34');
    error_log('[SPA REG] resolved_type: ' . $resolved_type);
    
    if ($resolved_type !== 'child') {
        return $form;
    }
    
    // Field 16 = required email (child)
    $child_email = rgpost('input_16');
    
    if (!empty(trim($child_email))) {
        error_log('[SPA REG] Child email already filled');
        return $form;
    }
    
    // Field 6.3 = First Name, 6.6 = Last Name
    $first_name = rgpost('input_6_3');
    $last_name = rgpost('input_6_6');
    
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
    $_POST['input_16'] = $generated_email;
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
    
    $resolved_type = rgpost('input_34');
    
    if ($resolved_type !== 'child') {
        return $result;
    }
    
    $generated_email = get_transient('spa_generated_child_email_' . $form['id']);
    
    if ($generated_email) {
        $result['is_valid'] = true;
        $result['message'] = '';
        $_POST['input_16'] = $generated_email;
        
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
    $resolved_type = rgpost('input_34');
    
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
    
    $resolved_type = rgar($entry, '34'); // spa_resolved_type
    
    error_log('[SPA SUBMISSION] Program: ' . rgar($entry, '2'));
    error_log('[SPA SUBMISSION] Type: ' . $resolved_type);
    
    // Načítaj údaje z formulára
    $first_name = rgar($entry, '6.3');
    $last_name = rgar($entry, '6.6');
    $birthdate = rgar($entry, '7'); // spa_member_birthdate
    $health_notes = rgar($entry, '9'); // spa_member_health_restrictions
    $address_street = rgar($entry, '17.1');
    $address_city = rgar($entry, '17.3');
    $address_zip = rgar($entry, '17.5');
    
    // CHILD flow
    if ($resolved_type === 'child') {
        $child_email = rgar($entry, '16');
        $parent_email = rgar($entry, '12');
        $parent_phone = rgar($entry, '13');
        $parent_first_name = rgar($entry, '18.3');
        $parent_last_name = rgar($entry, '18.6');
        $birth_number = rgar($entry, '8');
        
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
        $adult_email = rgar($entry, '16');
        $adult_phone = rgar($entry, '19');
        
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
        foreach ($form['fields'] as &$field) {
            if ($field->id == $field_id) {
                $field->failed_validation = true;
                $field->validation_message = $message;
                break;
            }
        }
    }

    /**
     * Helper: Konverzia dátumu z GF formátu (d.m.Y) na ISO (Y-m-d)
     */
    /*     function spa_convert_date_to_iso($date_string) {
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
    } */

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

    /**
     * Hlavná server-side validácia registrácie
     * Hook: gform_validation
     */
    function spa_validate_registration($validation_result) {
        $form = $validation_result['form'];
        
        // Získaj hodnoty z formulára
        $resolved_type = rgpost('input_34'); // spa_resolved_type
        $program_id = rgpost('input_2');     // spa_program
        $city_slug = rgpost('input_1');      // spa_city
        
        error_log('[SPA VALIDATION] Starting validation: type=' . $resolved_type . ', program_id=' . $program_id . ', city=' . $city_slug);
        
        // ========================================
        // VALIDÁCIA PROGRAMU A MIESTA
        // ========================================
        
        if (empty($program_id)) {
            $validation_result['is_valid'] = false;
            spa_set_field_error($form, 2, 'Musíte vybrať tréningový program.');
            error_log('[SPA VALIDATION] Program not selected');
        } else {
            // Overenie existencie programu
            $program = get_post($program_id);
            
            if (!$program || $program->post_status !== 'publish' || $program->post_type !== 'spa_group') {
                $validation_result['is_valid'] = false;
                spa_set_field_error($form, 2, 'Vybraný program neexistuje alebo nie je dostupný.');
                error_log('[SPA VALIDATION] Program does not exist: ' . $program_id);
            } else {
                // Overenie miesta programu
                $place_id = get_post_meta($program_id, 'spa_place_id', true);
                
                if (empty($place_id)) {
                    $validation_result['is_valid'] = false;
                    spa_set_field_error($form, 2, 'Program nemá priradené miesto tréningov.');
                    error_log('[SPA VALIDATION] Program has no place: ' . $program_id);
                } else {
                    // Overenie zhody mesta s miestom programu
                    $place_city = get_post_meta($place_id, 'spa_place_city', true);
                    $place_city_normalized = sanitize_title($place_city);
                    
                    if ($place_city_normalized !== $city_slug) {
                        $validation_result['is_valid'] = false;
                        spa_set_field_error($form, 2, 'Program nie je dostupný vo vybranom meste.');
                        error_log('[SPA VALIDATION] City mismatch: program_city=' . $place_city_normalized . ', selected_city=' . $city_slug);
                    }
                }
                
                // Overenie typu účastníka vs program
                $age_min = floatval(get_post_meta($program_id, 'spa_age_from', true));
                $is_child_program = $age_min < 18;
                
                if ($resolved_type === 'child' && !$is_child_program) {
                    $validation_result['is_valid'] = false;
                    spa_set_field_error($form, 2, 'Tento program je určený len pre dospelých (18+ rokov).');
                    error_log('[SPA VALIDATION] Child cannot register for adult program');
                }
                
                if ($resolved_type === 'adult' && $is_child_program) {
                    $validation_result['is_valid'] = false;
                    spa_set_field_error($form, 2, 'Tento program je určený len pre deti (mladších ako 18 rokov).');
                    error_log('[SPA VALIDATION] Adult cannot register for child program');
                }
            }
        }
        
        // ========================================
        // VALIDÁCIA PODĽA TYPU ÚČASTNÍKA
        // ========================================
        
        if ($resolved_type === 'child') {
            // CHILD: Povinné polia
            
            // Meno účastníka (6.3 = meno, 6.6 = priezvisko)
            $first_name = rgpost('input_6_3');
            $last_name = rgpost('input_6_6');
            
            if (empty($first_name) || empty($last_name)) {
                $validation_result['is_valid'] = false;
                spa_set_field_error($form, 6, 'Meno a priezvisko účastníka sú povinné.');
                error_log('[SPA VALIDATION] Child name missing');
            }
            
            // Dátum narodenia (field 7)
            $birthdate = rgpost('input_7');
            if (empty($birthdate)) {
                $validation_result['is_valid'] = false;
                spa_set_field_error($form, 7, 'Dátum narodenia je povinný.');
                error_log('[SPA VALIDATION] Child birthdate missing');
            }
            
            // Zákonný zástupca - meno (18.3 = meno, 18.6 = priezvisko)
            $guardian_first = rgpost('input_18_3');
            $guardian_last = rgpost('input_18_6');
            
            if (empty($guardian_first) || empty($guardian_last)) {
                $validation_result['is_valid'] = false;
                spa_set_field_error($form, 18, 'Meno a priezvisko zákonného zástupcu sú povinné.');
                error_log('[SPA VALIDATION] Guardian name missing');
            }
            
            // Zákonný zástupca - email (field 12)
            $guardian_email = rgpost('input_12');
            if (empty($guardian_email)) {
                $validation_result['is_valid'] = false;
                spa_set_field_error($form, 12, 'E-mail zákonného zástupcu je povinný.');
                error_log('[SPA VALIDATION] Guardian email missing');
            }
            
            // Zákonný zástupca - telefón (field 13)
            $guardian_phone = rgpost('input_13');
            if (empty($guardian_phone)) {
                $validation_result['is_valid'] = false;
                spa_set_field_error($form, 13, 'Telefón zákonného zástupcu je povinný.');
                error_log('[SPA VALIDATION] Guardian phone missing');
            }
            
            // Súhlasy (field 35 = checkbox group, 4 povinné checkboxy)
            $consent_gdpr = rgpost('input_35_1');
            $consent_health = rgpost('input_35_2');
            $consent_statutes = rgpost('input_35_3');
            $consent_terms = rgpost('input_35_4');
            
            if (empty($consent_gdpr) || empty($consent_health) || empty($consent_statutes) || empty($consent_terms)) {
                $validation_result['is_valid'] = false;
                spa_set_field_error($form, 35, 'Musíte súhlasiť so všetkými povinnými súhlasmi.');
                error_log('[SPA VALIDATION] Child consents missing');
            }
            
            // Checkbox 42 (potvrdenie zákonného zástupcu)
            $consent_guardian = rgpost('input_42_1');
            if (empty($consent_guardian)) {
                $validation_result['is_valid'] = false;
                spa_set_field_error($form, 42, 'Musíte potvrdiť, že ste zákonný zástupca dieťaťa.');
                error_log('[SPA VALIDATION] Guardian confirmation missing');
            }
            
        } elseif ($resolved_type === 'adult') {
            // ADULT: Povinné polia
            
            // Meno účastníka (6.3 = meno, 6.6 = priezvisko)
            $first_name = rgpost('input_6_3');
            $last_name = rgpost('input_6_6');
            
            if (empty($first_name) || empty($last_name)) {
                $validation_result['is_valid'] = false;
                spa_set_field_error($form, 6, 'Meno a priezvisko účastníka sú povinné.');
                error_log('[SPA VALIDATION] Adult name missing');
            }
            
            // Email (field 16 = required email pre adult)
            $adult_email = rgpost('input_16');
            if (empty($adult_email)) {
                $validation_result['is_valid'] = false;
                spa_set_field_error($form, 16, 'E-mail účastníka je povinný.');
                error_log('[SPA VALIDATION] Adult email missing');
            }
            
            // Súhlasy (field 35 = checkbox group, 4 povinné checkboxy)
            $consent_gdpr = rgpost('input_35_1');
            $consent_health = rgpost('input_35_2');
            $consent_statutes = rgpost('input_35_3');
            $consent_terms = rgpost('input_35_4');
            
            if (empty($consent_gdpr) || empty($consent_health) || empty($consent_statutes) || empty($consent_terms)) {
                $validation_result['is_valid'] = false;
                spa_set_field_error($form, 35, 'Musíte súhlasiť so všetkými povinnými súhlasmi.');
                error_log('[SPA VALIDATION] Adult consents missing');
            }
            
            // IGNOROVANÉ pre adult:
            // - field 7 (dátum narodenia)
            // - field 8 (rodné číslo)
            // - field 18 (guardian name)
            // - field 12 (guardian email)
            // - field 13 (guardian phone)
            // - field 42 (checkbox guardian confirmation)
        }
        
        // Aktualizuj form v validation_result
        $validation_result['form'] = $form;
        
        error_log('[SPA VALIDATION] Final result: ' . ($validation_result['is_valid'] ? 'VALID' : 'INVALID'));
        
        return $validation_result;
    }

    /**
     * Helper: Nastavenie chyby pre field
     */
    function spa_set_field_error(&$form, $field_id, $message) {
        foreach ($form['fields'] as &$field) {
            if ($field->id == $field_id) {
                $field->failed_validation = true;
                $field->validation_message = $message;
                break;
            }
        }
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
     * Označenie validation errorov pre JS
     * Nastaví window.spaErrorState.errorType = 'validation'
     */
    function spa_mark_validation_errors($message, $form) {
        $message .= '<script>
            if (window.spaErrorState) {
                window.spaErrorState.errorType = "validation";
                console.log("[SPA] GF Validation error type set to: validation");
            }
        </script>';
        
        return $message;
    }

    /**
     * Označenie validation errorov pre JS
     * Nastaví window.spaErrorState.errorType = 'validation'
     */
    function spa_mark_validation_errors($message, $form) {
        $message .= '<script>
            if (window.spaErrorState) {
                window.spaErrorState.errorType = "validation";
                console.log("[SPA] GF Validation error type set to: validation");
            }
        </script>';
        
        return $message;
    }

    /**
     * Helper: Nastavenie chyby pre field
     */
    function spa_set_field_error(&$form, $field_id, $message) {
        foreach ($form['fields'] as &$field) {
            if ($field->id == $field_id) {
                $field->failed_validation = true;
                $field->validation_message = $message;
                break;
            }
        }
    }
        /**
     * FALLBACK: Prázdne funkcie pre staré hooky v WP cache
     * TODO: Odstrániť po vyčistení WP cache
     */
    if (!function_exists('spa_validate_required_consents')) {
        function spa_validate_required_consents($validation_result) {
            return $validation_result;
        }
    }

    if (!function_exists('spa_mark_validation_errors')) {
        function spa_mark_validation_errors($message, $form) {
            return $message;
        }
    }

    if (!function_exists('spa_set_field_error')) {
        function spa_set_field_error(&$form, $field_id, $message) {
            // Prázdna funkcia
        }
    }
}