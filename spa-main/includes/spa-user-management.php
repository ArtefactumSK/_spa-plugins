<?php
/**
 * SPA User Management
 * Tvorba a správa WP userov (rodič, dieťa)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook na GF submission – vstupný bod pre tvorbu userov
 */
function spa_user_management_init() {
    add_action('gform_after_submission', 'spa_handle_registration_submission', 10, 2);
}

/**
 * Spracovanie GF submissonu – KONTROLOVANÝ VSTUPNÝ BOD
 * 
 * @param array $entry GF entry data
 * @param array $form GF form object
 */
function spa_handle_registration_submission($entry, $form) {
    // LOG: Základné info o entry
    error_log('[SPA User Management] Entry received: ID=' . rgar($entry, 'id'));
    error_log('[SPA User Management] Form ID: ' . $form['id']);
    
    // Načítaj field config
    $config = spa_load_field_config();
    
    // Určenie typu účastníka (input_4)
    $registration_type = rgar($entry, '4');
    error_log('[SPA User Management] Registration type: ' . $registration_type);
    
    // Základné dáta z entry
    // E-maily podľa field ID a typu registrácie
    $parent_email = '';
    $child_email_final = '';
    $adult_email = '';
    
    if ($registration_type === 'child') {
        // CHILD MODE
        // Parent údaje z POST (entry môže byť prázdne pri async spracovaní)
        $parent_email_raw = rgpost('input_12');
        $parent_email_entry = rgar($entry, '12');
        
        $parent_first_raw = trim(rgpost('input_18_3'));
        $parent_last_raw = trim(rgpost('input_18_6'));
        
        error_log('[SPA MAP RAW] input_12=' . ($parent_email_raw ?: 'EMPTY'));
        error_log('[SPA MAP ENTRY] entry_12=' . ($parent_email_entry ?: 'EMPTY'));
        error_log('[SPA MAP RAW] parent_first=' . ($parent_first_raw ?: 'EMPTY'));
        error_log('[SPA MAP RAW] parent_last=' . ($parent_last_raw ?: 'EMPTY'));
        
        // Použiť POST ako primárny zdroj
        $parent_email = !empty($parent_email_raw) ? $parent_email_raw : $parent_email_entry;
        
        $child_email_input = rgar($entry, '15'); // input_15 (spa_client_email)
        
        // Child email: input_15 ALEBO vygenerovať
        if (!empty($child_email_input)) {
            $child_email_final = $child_email_input;
            $child_email_source = 'input_15';
        } else {
            $first = rgar($entry, '6.3');
            $last = rgar($entry, '6.6');
            
            if (!empty($first) && !empty($last)) {
                $child_email_final = spa_generate_child_email($first, $last);
                $child_email_source = 'generated';
            }
        }
        
        // LOG pre CHILD
        error_log('[SPA MAP] parent_email=' . ($parent_email ?: 'EMPTY'));
        error_log('[SPA MAP] child_email=' . ($child_email_final ?: 'EMPTY') . ' (source: ' . $child_email_source . ')');
        
    } elseif ($registration_type === 'adult') {
        // ADULT MODE
        $adult_email = rgar($entry, '16'); // input_16 (spa_client_email_required)
        
        // LOG pre ADULT
        error_log('[SPA MAP] adult_email=' . ($adult_email ?: 'EMPTY'));
    }
    
    // Základné dáta z entry
    $entry_data = [
        'registration_type' => $registration_type,
        'member_name_first' => rgar($entry, '6.3'),
        'member_name_last' => rgar($entry, '6.6'),
        'member_birthdate' => rgar($entry, '7'),
        'member_birthnumber' => rgar($entry, '8'),
        'client_address' => [
            'street' => rgar($entry, '17.1'),
            'city' => rgar($entry, '17.3'),
            'zip' => rgar($entry, '17.5'),
        ],
        'client_phone' => rgar($entry, '19'),
        'member_email' => $child_email_final,
        'member_email_adult' => $adult_email,
        'guardian_name_first' => ($registration_type === 'child' && !empty($parent_first_raw)) ? $parent_first_raw : rgar($entry, '18.3'),
        'guardian_name_last' => ($registration_type === 'child' && !empty($parent_last_raw)) ? $parent_last_raw : rgar($entry, '18.6'),
        'guardian_email' => $parent_email,
        'guardian_phone' => rgpost('input_13') ?: rgar($entry, '13'),
    ];
    
    error_log('[SPA User Management] Entry data: ' . print_r($entry_data, true));
    
    // Volanie skeleton funkcií
    if ($registration_type === 'child') {
        // CHILD FLOW: najprv parent, potom child
        $parent_user_id = spa_create_parent_user_skeleton($entry_data);
        
        if ($parent_user_id) {
            // Dočasne upravíme entry_data pre child
            $entry_data['parent_user_id'] = $parent_user_id;
            $child_user_id = spa_create_child_user_skeleton($entry_data);
            
            if ($child_user_id) {
                error_log('[SPA REGISTRATION] SUCCESS: Parent=' . $parent_user_id . ', Child=' . $child_user_id);
            }
        }
    } elseif ($registration_type === 'adult') {
        // ADULT FLOW - vytvor adult usera (spa_client)
        $adult_data = spa_prepare_adult_user_data($entry_data);
        
        if ($adult_data) {
            $user_id = spa_get_or_create_adult_user($adult_data['user_data'], $adult_data['meta_data']);
            
            if ($user_id) {
                error_log('[SPA REGISTRATION] SUCCESS: Adult user=' . $user_id);
            }
        }
    }
}

/**
 * SKELETON: Tvorba rodiča (WP user s email/heslo loginom)
 * 
 * @param array $data Dáta z GF entry
 * @return int|false User ID alebo false
 */
function spa_create_parent_user_skeleton($data) {
    error_log('[SPA Parent User] === START SKELETON ===');
    
    // Pre CHILD: parent email je povinný (input_12)
    // Pre ADULT: použiť member_email_adult (input_16)
    $email = null;
    
    if ($data['registration_type'] === 'child') {
        $email = $data['guardian_email'];
        
        if (empty($email)) {
            error_log('[SPA Parent User] ERROR: Missing parent email (input_12)');
            return false;
        }
    } elseif ($data['registration_type'] === 'adult') {
        $email = $data['member_email_adult'];
        
        if (empty($email)) {
            error_log('[SPA Parent User] ERROR: Missing adult email (input_16)');
            return false;
        }
    }
    
    // Validácia mena
    $first_name = ($data['registration_type'] === 'child') 
        ? $data['guardian_name_first'] 
        : $data['member_name_first'];
    
    $last_name = ($data['registration_type'] === 'child') 
        ? $data['guardian_name_last'] 
        : $data['member_name_last'];
    
    if (empty($first_name) || empty($last_name)) {
        error_log('[SPA Parent User] ERROR: Missing name');
        return false;
    }
    
    if (empty($data['guardian_name_first']) || empty($data['guardian_name_last'])) {
        error_log('[SPA Parent User] ERROR: Missing guardian name');
        return false;
    }
    
    // Kontrola duplicity
    $existing_user = get_user_by('email', $email);
    if ($existing_user) {
        error_log('[SPA Parent User] User already exists: ID=' . $existing_user->ID);
        return $existing_user->ID;
    }
    
    // Príprava dát pre wp_insert_user
    $user_data = [
        'user_login' => sanitize_user($email),
        'user_email' => sanitize_email($email),
        'first_name' => sanitize_text_field($first_name),
        'last_name' => sanitize_text_field($last_name),
        'display_name' => sanitize_text_field($first_name . ' ' . $last_name),
        'role' => 'spa_parent', // TODO: Vytvoriť custom role 'spa_parent'
        'user_pass' => wp_generate_password(12, true, true),
    ];
    
    error_log('[SPA Parent User] Prepared data: ' . print_r($user_data, true));
    
    // Príprava meta dát
    $meta_data = [];
    
    if (!empty($data['member_birthdate'])) {
        $meta_data['birthdate'] = sanitize_text_field($data['member_birthdate']);
    }
    
    if (!empty($data['client_phone'])) {
        $meta_data['phone'] = $data['client_phone'];
    }
    
    if (isset($data['client_address']) && is_array($data['client_address'])) {
        if (!empty($data['client_address']['street'])) {
            $meta_data['address_street'] = $data['client_address']['street'];
        }
        if (!empty($data['client_address']['city'])) {
            $meta_data['address_city'] = $data['client_address']['city'];
        }
        if (!empty($data['client_address']['zip'])) {
            $meta_data['address_zip'] = $data['client_address']['zip'];
        }
    }
    
    // Vytvorenie alebo získanie usera
    $user_id = spa_get_or_create_parent_user($user_data, $meta_data);
    
    if (!$user_id) {
        error_log('[SPA Parent User] === END (FAILED) ===');
        return false;
    }
    
    // TODO: Poslať email s heslom
    // TODO: Uložiť telefón do user meta
    
    error_log('[SPA Parent User] === END (SUCCESS) ===');
    
    return $user_id;
}

/**
 * SKELETON: Tvorba dieťaťa (WP user s PIN loginom)
 * 
 * @param array $data Dáta z GF entry
 * @return int|false User ID alebo false
 */
function spa_create_child_user_skeleton($data) {
    error_log('[SPA Child User] === START SKELETON ===');
    
    // Získaj parent_user_id z dát
    $parent_user_id = isset($data['parent_user_id']) ? $data['parent_user_id'] : null;
    
    // Validácia vstupných dát
    if (empty($data['member_email'])) {
        error_log('[SPA Child User] ERROR: Missing member email');
        return false;
    }
    
    if (empty($data['member_name_first']) || empty($data['member_name_last'])) {
        error_log('[SPA Child User] ERROR: Missing member name');
        return false;
    }
    
    // Kontrola duplicity
    $existing_user = get_user_by('email', $data['member_email']);
    if ($existing_user) {
        error_log('[SPA Child User] User already exists: ID=' . $existing_user->ID);
        return $existing_user->ID;
    }
    
    // Príprava dát pre wp_insert_user
    $user_data = [
        'user_login' => sanitize_user($data['member_email']),
        'user_email' => sanitize_email($data['member_email']),
        'first_name' => sanitize_text_field($data['member_name_first']),
        'last_name' => sanitize_text_field($data['member_name_last']),
        'display_name' => sanitize_text_field($data['member_name_first'] . ' ' . $data['member_name_last']),
        'role' => 'spa_child', // TODO: Vytvoriť custom role 'spa_child'
        'user_pass' => wp_generate_password(12, true, true), // TODO: Nahradiť PIN generovaním
    ];
    
    error_log('[SPA Child User] Prepared data: ' . print_r($user_data, true));
    
    if (!$parent_user_id) {
        error_log('[SPA Child User] ERROR: Cannot create child without parent_user_id');
        error_log('[SPA Child User] === END (FAILED - NO PARENT) ===');
        return false;
    }
    
    // Príprava meta dát
    $meta_data = [];
    
    if (!empty($data['member_birthdate'])) {
        $meta_data['birthdate'] = $data['member_birthdate'];
    }
    
    if (!empty($data['member_birthnumber'])) {
        $meta_data['birth_number'] = $data['member_birthnumber'];
    }
    
    // Vytvorenie alebo získanie usera
    $user_id = spa_get_or_create_child_user($user_data, $parent_user_id, $meta_data);
    
    if (!$user_id) {
        error_log('[SPA Child User] === END (FAILED) ===');
        return false;
    }
    
    // TODO: Vygenerovať 4-miestny PIN
    // TODO: Uložiť PIN do user meta (sha256 hash)
    // TODO: Poslať rodičovi email s PIN kódom dieťaťa
    
    error_log('[SPA Child User] === END (SUCCESS) ===');
    
    return $user_id;
}


/**
 * Generovanie child emailu: meno.priezvisko@piaseckyacademy.sk
 * 
 * @param string $first_name Meno
 * @param string $last_name Priezvisko
 * @return string Email
 */
function spa_generate_child_email($first_name, $last_name) {
    // Lowercase najprv
    $first_lower = strtolower($first_name);
    $last_lower = strtolower($last_name);
    
    // Odstránenie diakritiky
    $first_no_accents = remove_accents($first_lower);
    $last_no_accents = remove_accents($last_lower);
    
    // Odstránenie nealfanumerických znakov
    $first_clean = preg_replace('/[^a-z0-9]/', '', $first_no_accents);
    $last_clean = preg_replace('/[^a-z0-9]/', '', $last_no_accents);
    
    return $first_clean . '.' . $last_clean . '@piaseckyacademy.sk';
}

/**
 * Príprava dát pre adult usera
 */
function spa_prepare_adult_user_data($data) {
    if (empty($data['member_email_adult'])) {
        error_log('[SPA ERROR] Adult email missing in prepare');
        return false;
    }
    
    $user_data = [
        'user_login' => sanitize_user($data['member_email_adult']),
        'user_email' => sanitize_email($data['member_email_adult']),
        'first_name' => sanitize_text_field($data['member_name_first']),
        'last_name' => sanitize_text_field($data['member_name_last']),
        'display_name' => sanitize_text_field($data['member_name_first'] . ' ' . $data['member_name_last']),
        'role' => 'spa_client',
        'user_pass' => wp_generate_password(12, true, true),
    ];
    
    $meta_data = [];
    
    if (!empty($data['client_phone'])) {
        $meta_data['phone'] = $data['client_phone'];
    }
    
    if (isset($data['client_address']) && is_array($data['client_address'])) {
        if (!empty($data['client_address']['street'])) {
            $meta_data['address_street'] = $data['client_address']['street'];
        }
        if (!empty($data['client_address']['city'])) {
            $meta_data['address_city'] = $data['client_address']['city'];
        }
        if (!empty($data['client_address']['zip'])) {
            $meta_data['address_zip'] = $data['client_address']['zip'];
        }
    }
    
    return [
        'user_data' => $user_data,
        'meta_data' => $meta_data,
    ];
}