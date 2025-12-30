<?php
/**
 * SPA System MAIN Core
 * Centrálna logika pre spracovanie registračných dát MAIN
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vytvorenie normalizovaného registračného objektu z GF entry
 * 
 * @param array $entry GF entry data
 * @return array Normalizovaný registračný objekt
 */
function spa_create_registration_object($entry) {
    $registration = [
        'program' => '',
        'variant' => '',
        'client_email' => '',
        'client_phone' => '',
        'client_address' => [],
        'consents' => [],
        'raw_entry_id' => rgar($entry, 'id'),
        'created_at' => current_time('mysql'),
    ];
    
    // Program a variant
    $registration['program'] = spa_get_field_value($entry, 'program');
    $registration['variant'] = spa_get_field_value($entry, 'variant');
    
    // Kontaktné údaje
    $registration['client_email'] = spa_get_field_value($entry, 'client_email');
    $registration['client_phone'] = spa_get_field_value($entry, 'client_phone');
    
    // Adresa (GF Address field vracia array)
    $address_raw = spa_get_field_value($entry, 'client_address');
    if (is_array($address_raw)) {
        $registration['client_address'] = $address_raw;
    }
    
    // Súhlasy
    $consent_fields = [
        'consent_gdpr',
        'consent_health',
        'consent_statutes',
        'consent_terms',
        'consent_guardian',
        'consent_marketing',
    ];
    
    foreach ($consent_fields as $consent) {
        $registration['consents'][$consent] = spa_is_consent_checked($entry, $consent);
    }
    
    return $registration;
}

/**
 * Validácia registračného objektu
 * Kontroluje povinné polia a formát údajov
 * 
 * @param array $registration Registračný objekt
 * @return array ['valid' => bool, 'errors' => array]
 */
function spa_validate_registration($registration) {
    $errors = [];
    
    // Kontrola program
    if (empty($registration['program'])) {
        $errors['program'] = 'Program je povinný.';
    }
    
    // Kontrola variant
    if (empty($registration['variant'])) {
        $errors['variant'] = 'Variant je povinný.';
    }
    
    // Kontrola email
    if (empty($registration['client_email'])) {
        $errors['client_email'] = 'Email je povinný.';
    } elseif (!spa_validate_email($registration['client_email'])) {
        $errors['client_email'] = 'Email nie je v správnom formáte.';
    }
    
    // Kontrola telefón
    if (empty($registration['client_phone'])) {
        $errors['client_phone'] = 'Telefón je povinný.';
    } elseif (!spa_validate_phone($registration['client_phone'])) {
        $errors['client_phone'] = 'Telefón nie je v správnom formáte.';
    }
    
    // Kontrola adresa
    if (empty($registration['client_address']) || !spa_validate_address($registration['client_address'])) {
        $errors['client_address'] = 'Adresa musí obsahovať ulicu, mesto a PSČ.';
    }
    
    // Kontrola povinných súhlasov
    $required_consents = [
        'consent_gdpr' => 'GDPR súhlas je povinný.',
        'consent_health' => 'Zdravotný súhlas je povinný.',
        'consent_statutes' => 'Súhlas so stanovami je povinný.',
        'consent_terms' => 'Súhlas s podmienkami je povinný.',
    ];
    
    foreach ($required_consents as $consent => $error_message) {
        if (empty($registration['consents'][$consent])) {
            $errors[$consent] = $error_message;
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
    ];
}

/**
 * Spracovanie registrácie
 * Hlavná funkcia pre spracovanie validovanej registrácie
 * 
 * @param array $registration Validovaný registračný objekt
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function spa_process_registration($registration) {
    // Log registrácie
    spa_log('Processing registration', $registration);
    
    // V tejto fáze len vrátime úspech a pripravené dáta
    // Ďalšie moduly (scheduler, platby, účty) budú implementované neskôr
    
    return [
        'success' => true,
        'message' => 'Registrácia bola úspešne spracovaná.',
        'data' => $registration,
    ];
}

/**
 * ============================================
 * AJAX ENDPOINTY PRE DYNAMICKÉ SELECTY
 * ============================================
 */

/**
 * AJAX: Získanie zoznamu miest
 */
function spa_ajax_get_cities() {
    // Dummy data (pripravené na budúce DB/CPT)
    $cities = [
        ['id' => 1, 'name' => 'Malacky'],
        ['id' => 2, 'name' => 'Košice'],
    ];
    
    wp_send_json_success($cities);
}
add_action('wp_ajax_spa_get_cities', 'spa_ajax_get_cities');
add_action('wp_ajax_nopriv_spa_get_cities', 'spa_ajax_get_cities');

/**
 * AJAX: Získanie programov podľa mesta
 */
function spa_ajax_get_programs() {
    // Validácia vstupu
    $city_id = isset($_POST['city_id']) ? intval($_POST['city_id']) : 0;
    
    if ($city_id === 0) {
        wp_send_json_error(['message' => 'Neplatné ID mesta.']);
        return;
    }
    
    // Dummy data (pripravené na budúce DB/CPT)
    $all_programs = [
        ['id' => 10, 'name' => 'Mladý akrobati', 'city_id' => 1],
        ['id' => 11, 'name' => 'Little Big Heroes', 'city_id' => 1],
        ['id' => 20, 'name' => 'SPA Juniori', 'city_id' => 2],
    ];
    
    // Filtrácia podľa city_id
    $programs = array_filter($all_programs, function($program) use ($city_id) {
        return $program['city_id'] === $city_id;
    });
    
    // Reset indexov
    $programs = array_values($programs);
    
    if (empty($programs)) {
        wp_send_json_error(['message' => 'Pre toto mesto nie sú dostupné žiadne programy.']);
        return;
    }
    
    wp_send_json_success($programs);
}
add_action('wp_ajax_spa_get_programs', 'spa_ajax_get_programs');
add_action('wp_ajax_nopriv_spa_get_programs', 'spa_ajax_get_programs');