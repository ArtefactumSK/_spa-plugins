<?php
/**
 * SPA User Creation
 * Bezpečné vytváranie a párovanie WP userov
 */

if (!defined('ABSPATH')) {
    exit;
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
    
    $user_id = wp_insert_user($data);
    
    if (is_wp_error($user_id)) {
        error_log('[SPA ERROR] Child creation failed - ' . $user_id->get_error_message());
        return false;
    }
    
    error_log('[SPA USER] CHILD created user_id=' . $user_id);
    
    update_user_meta($user_id, 'parent_user_id', $parent_user_id);
    
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
    }
    
    if (!empty($meta_data['birth_number'])) {
        update_user_meta($user_id, 'birth_number', sanitize_text_field($meta_data['birth_number']));
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
    $existing_pin = get_user_meta($user_id, 'pin', true);
    
    if (!empty($existing_pin)) {
        error_log('[SPA PIN] existing pin=' . $existing_pin . ' for user_id=' . $user_id);
        return $existing_pin;
    }
    
    $pin = sprintf('%03d', wp_rand(100, 999));
    
    update_user_meta($user_id, 'pin', $pin);
    
    error_log('[SPA PIN] generated pin=' . $pin . ' for user_id=' . $user_id);
    
    return $pin;
}

/**
 * Generovanie a uloženie variabilného symbolu (VS)
 */
function spa_generate_and_store_vs($user_id) {
    $existing_vs = get_user_meta($user_id, 'variable_symbol', true);
    
    if (!empty($existing_vs)) {
        if (strlen($existing_vs) === 3) {
            $new_vs = '1' . $existing_vs;
            update_user_meta($user_id, 'variable_symbol', $new_vs);
            error_log('[SPA VS] upgraded 3-digit vs=' . $new_vs . ' for user_id=' . $user_id);
            return $new_vs;
        }
        
        error_log('[SPA VS] existing vs=' . $existing_vs . ' for user_id=' . $user_id);
        return $existing_vs;
    }
    
    global $wpdb;
    
    $max_attempts = 100;
    $attempt = 0;
    
    while ($attempt < $max_attempts) {
        $vs = wp_rand(1000, 9999);
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'variable_symbol' 
             AND meta_value = %s",
            $vs
        ));
        
        if (!$exists) {
            update_user_meta($user_id, 'variable_symbol', $vs);
            error_log('[SPA VS] generated vs=' . $vs . ' for user_id=' . $user_id);
            return $vs;
        }
        
        $attempt++;
    }
    
    error_log('[SPA ERROR] VS generation failed after ' . $max_attempts . ' attempts for user_id=' . $user_id);
    return false;
}