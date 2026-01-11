<?php
/**
 * SPA User Creation
 * Bezpečné vytváranie a párovanie WP userov
 */

if (!defined('ABSPATH')) {
    exit;
}
// ✅ Zabezpečenie proti duplicite s témou
if (!function_exists('spa_generate_unique_username')) {
    /**
     * Generovanie unikátneho username
     * Fallback ak téma nedefinuje túto funkciu
     */
    function spa_generate_unique_username($base) {
        $username = sanitize_user($base, true);
        $counter = 1;
        
        while (username_exists($username)) {
            $username = sanitize_user($base . $counter, true);
            $counter++;
        }
        
        return $username;
    }
}
/**
 * Získanie alebo vytvorenie parent usera
 * 
 * @param array $data Pripravené user dáta zo skeletonu
 * @param array $meta_data Dodatočné meta dáta (telefón, adresa)
 * @return int|false User ID alebo false pri chybe
 */
function spa_get_or_create_parent_user($data, $meta_data = []) {
    if (empty($data['user_email'])) {
        error_log('[SPA ERROR] Parent email missing');
        return false;
    }
    
    $existing_user = get_user_by('email', $data['user_email']);
    
    if ($existing_user) {
        error_log('[SPA USER] PARENT existing user_id=' . $existing_user->ID);
        spa_update_parent_meta($existing_user->ID, $meta_data);
        return $existing_user->ID;
    }
    
    $data['user_login'] = spa_generate_unique_username($data['first_name'], $data['last_name']);
    $user_id = wp_insert_user($data);
    
    if (is_wp_error($user_id)) {
        error_log('[SPA ERROR] Parent creation failed - ' . $user_id->get_error_message());
        return false;
    }
    
    error_log('[SPA USER] PARENT created user_id=' . $user_id);
    
    spa_update_parent_meta($user_id, $meta_data);
    
    return $user_id;
}

/**
 * Získanie alebo vytvorenie child usera
 * 
 * @param array $data Pripravené user dáta zo skeletonu
 * @param int $parent_user_id ID rodiča
 * @param array $meta_data Dodatočné meta dáta (birthdate, birth_number)
 * @return int|false User ID alebo false pri chybe
 */
function spa_get_or_create_child_user($data, $parent_user_id, $meta_data = []) {
    if (empty($data['user_email'])) {
        error_log('[SPA ERROR] Child email missing');
        return false;
    }
    
    if (empty($parent_user_id)) {
        error_log('[SPA ERROR] Parent user_id missing for child');
        return false;
    }
    
    $existing_user = get_user_by('email', $data['user_email']);
    
    if ($existing_user) {
        error_log('[SPA USER] CHILD existing user_id=' . $existing_user->ID);
        update_user_meta($existing_user->ID, 'parent_user_id', $parent_user_id);
        spa_update_child_meta($existing_user->ID, $meta_data);
        return $existing_user->ID;
    }
    
    $data['user_login'] = spa_generate_unique_username($data['first_name'], $data['last_name']);
    $user_id = wp_insert_user($data);
    
    if (is_wp_error($user_id)) {
        error_log('[SPA ERROR] Child creation failed - ' . $user_id->get_error_message());
        return false;
    }
    
    error_log('[SPA USER] CHILD created user_id=' . $user_id);
    
    update_user_meta($user_id, 'parent_id', $parent_user_id);
    error_log('[SPA META] parent_id saved child=' . $user_id . ' parent=' . $parent_user_id);
    
    spa_update_child_meta($user_id, $meta_data);
    
    spa_generate_and_store_pin($user_id);
    spa_generate_and_store_vs($user_id);
    
    return $user_id;
}

/**
 * Získanie alebo vytvorenie adult usera (spa_client)
 * 
 * @param array $data Pripravené user dáta zo skeletonu
 * @param array $meta_data Dodatočné meta dáta
 * @return int|false User ID alebo false pri chybe
 */
function spa_get_or_create_adult_user($data, $meta_data = []) {
    if (empty($data['user_email'])) {
        error_log('[SPA ERROR] Adult email missing');
        return false;
    }
    
    $existing_user = get_user_by('email', $data['user_email']);
    
    if ($existing_user) {
        error_log('[SPA USER] ADULT existing user_id=' . $existing_user->ID);
        spa_update_adult_meta($existing_user->ID, $meta_data);
        return $existing_user->ID;
    }
    
    $data['user_login'] = spa_generate_unique_username($data['first_name'], $data['last_name']);
    $user_id = wp_insert_user($data);
    
    if (is_wp_error($user_id)) {
        error_log('[SPA ERROR] Adult creation failed - ' . $user_id->get_error_message());
        return false;
    }
    
    error_log('[SPA USER] ADULT created user_id=' . $user_id);
    
    spa_update_adult_meta($user_id, $meta_data);
    
    return $user_id;
}

/**
 * Aktualizácia parent meta
 */
function spa_update_parent_meta($user_id, $meta_data) {
    if (!empty($meta_data['phone'])) {
        update_user_meta($user_id, 'phone', sanitize_text_field($meta_data['phone']));
    }
    
    if (!empty($meta_data['address_street'])) {
        update_user_meta($user_id, 'address_street', sanitize_text_field($meta_data['address_street']));
    }
    
    if (!empty($meta_data['address_city'])) {
        update_user_meta($user_id, 'address_city', sanitize_text_field($meta_data['address_city']));
    }
    
    if (!empty($meta_data['address_zip'])) {
        update_user_meta($user_id, 'address_zip', sanitize_text_field($meta_data['address_zip']));
    }
}

/**
 * Aktualizácia child meta
 */
function spa_update_child_meta($user_id, $meta_data) {
    if (!empty($meta_data['birthdate'])) {
        update_user_meta($user_id, 'birthdate', sanitize_text_field($meta_data['birthdate']));
        error_log('[SPA META] birthdate saved for user_id=' . $user_id);
    }
    
    if (!empty($meta_data['birth_number'])) {
        $birth_number_clean = preg_replace('/[^0-9]/', '', $meta_data['birth_number']);
        update_user_meta($user_id, 'rodne_cislo', $birth_number_clean);
        error_log('[SPA META] rodne_cislo saved for user_id=' . $user_id);
    }
}

/**
 * Aktualizácia adult meta
 */
function spa_update_adult_meta($user_id, $meta_data) {
    if (!empty($meta_data['phone'])) {
        update_user_meta($user_id, 'phone', sanitize_text_field($meta_data['phone']));
    }
    
    if (!empty($meta_data['address_street'])) {
        update_user_meta($user_id, 'address_street', sanitize_text_field($meta_data['address_street']));
    }
    
    if (!empty($meta_data['address_city'])) {
        update_user_meta($user_id, 'address_city', sanitize_text_field($meta_data['address_city']));
    }
    
    if (!empty($meta_data['address_zip'])) {
        update_user_meta($user_id, 'address_zip', sanitize_text_field($meta_data['address_zip']));
    }
}

/**
 * Generovanie a uloženie PIN pre child
 */
function spa_generate_and_store_pin($user_id) {
    $existing_pin_hash = get_user_meta($user_id, 'spa_pin', true);
    
    if (!empty($existing_pin_hash)) {
        $existing_pin_plain = get_user_meta($user_id, 'spa_pin_plain', true);
        error_log('[SPA PIN] existing for user_id=' . $user_id);
        return $existing_pin_plain;
    }
    
    if (function_exists('spa_generate_pin')) {
        $pin = spa_generate_pin();
    } else {
        $pin = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    if (function_exists('spa_hash_pin')) {
        $pin_hash = spa_hash_pin($pin);
    } else {
        $pin_hash = wp_hash_password($pin);
    }
    
    update_user_meta($user_id, 'spa_pin', $pin_hash);
    update_user_meta($user_id, 'spa_pin_plain', $pin);
    
    error_log('[SPA META] spa_pin saved for child_id=' . $user_id);
    error_log('[SPA META] spa_pin_plain saved for child_id=' . $user_id);
    
    return $pin;
}

/**
 * Generovanie a uloženie variabilného symbolu (VS)
 */
function spa_generate_and_store_vs($user_id) {
    $existing_vs = get_user_meta($user_id, 'variabilny_symbol', true);
    
    if (!empty($existing_vs)) {
        error_log('[SPA META] variabilny_symbol existing value=' . $existing_vs . ' for user_id=' . $user_id);
        return $existing_vs;
    }
    
    if (function_exists('spa_generate_variabilny_symbol')) {
        $vs = spa_generate_variabilny_symbol();
    } else {
        global $wpdb;
        
        $max_vs = $wpdb->get_var("
            SELECT MAX(CAST(meta_value AS UNSIGNED)) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'variabilny_symbol'
            AND meta_value REGEXP '^[0-9]+$'
        ");
        
        $vs = $max_vs ? intval($max_vs) + 1 : 100;
        
        if ($vs < 100) {
            $vs = 100;
        }
        
        $vs = str_pad($vs, 3, '0', STR_PAD_LEFT);
    }
    
    update_user_meta($user_id, 'variabilny_symbol', $vs);
    
    error_log('[SPA META] variabilny_symbol saved value=' . $vs . ' for user_id=' . $user_id);
    
    return $vs;
}