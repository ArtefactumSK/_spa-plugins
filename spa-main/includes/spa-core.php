<?php
/**
 * SPA System Core
 * Centrálna logika pre spracovanie registračných dát
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
    
    // Program a frekvencia (nie variant!)
    $registration['program'] = spa_get_field_value($entry, 'spa_program');
    $registration['frequency'] = spa_get_field_value($entry, 'spa_frequency');

    // Kontaktné údaje
    $registration['client_email'] = spa_get_field_value($entry, 'spa_client_email');
    $registration['client_phone'] = spa_get_field_value($entry, 'spa_client_phone');
    
    // Adresa (GF Address field vracia array)
    $address_raw = spa_get_field_value($entry, 'spa_client_address');
    if (is_array($address_raw)) {
        $registration['client_address'] = $address_raw;
    }
    
    // Súhlasy
    $consent_fields = [
        'spa_consent_gdpr',
        'spa_consent_health',
        'spa_consent_statutes',
        'spa_consent_terms',
        'spa_consent_guardian',
        'spa_consent_marketing',
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
    
    // Kontrola frekvencie
    if (empty($registration['frequency'])) {
        $errors['frequency'] = 'Frekvencia je povinná.';
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
        'spa_consent_gdpr' => 'GDPR súhlas je povinný.',
        'spa_consent_health' => 'Zdravotný súhlas je povinný.',
        'spa_consent_statutes' => 'Súhlas so stanovami je povinný.',
        'spa_consent_terms' => 'Súhlas s podmienkami je povinný.',
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
 * AJAX ENDPOINTY PRE DYNAMICKÉ SELECTY (CPT)
 * Dynamické načítanie z spa_place a spa_group
 * ============================================
 */

/**
 * AJAX: Získanie zoznamu miest z CPT spa_place
 */
function spa_ajax_get_cities() {
    global $wpdb;
    
    // SQL: Unikátne mestá z publikovaných spa_place
    $sql = "
        SELECT DISTINCT pm.meta_value as city_name
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE p.post_type = 'spa_place'
        AND p.post_status = 'publish'
        AND pm.meta_key = 'spa_place_city'
        AND pm.meta_value != ''
        ORDER BY pm.meta_value ASC
    ";
    
    $results = $wpdb->get_results($sql);
    
    if (empty($results)) {
        wp_send_json_error(['message' => 'Nenašli sa žiadne aktívne mestá.']);
        return;
    }
    
    // Formátovanie pre JS
    $cities = [];
    foreach ($results as $row) {
        $cities[] = [
            'id' => sanitize_title($row->city_name),
            'name' => $row->city_name,
        ];
    }
    
    wp_send_json_success($cities);
}
add_action('wp_ajax_spa_get_cities', 'spa_ajax_get_cities');
add_action('wp_ajax_nopriv_spa_get_cities', 'spa_ajax_get_cities');

/**
 * AJAX: Získanie programov podľa mesta
 * Načítava z CPT spa_group (publikované)
 */
function spa_ajax_get_programs() {
    $city_slug = isset($_POST['city_id']) ? sanitize_text_field($_POST['city_id']) : '';
    
    if (empty($city_slug)) {
        wp_send_json_error(['message' => 'Neplatné ID mesta.']);
        return;
    }
    
    // Konverzia slug → názov mesta
    $city_name = ucfirst(str_replace('-', ' ', $city_slug));
    
    // Načítanie programov pre dané mesto
    $programs = spa_get_programs_for_city_dynamic($city_name);
    
    if (empty($programs)) {
        wp_send_json_error(['message' => 'Pre toto mesto nie sú dostupné žiadne programy.']);
        return;
    }
    
    wp_send_json_success($programs);
}
add_action('wp_ajax_spa_get_programs', 'spa_ajax_get_programs');
add_action('wp_ajax_nopriv_spa_get_programs', 'spa_ajax_get_programs');

/**
 * Helper: Získanie programov pre dané mesto (DYNAMICKY z CPT)
 * Vracia programy s vekom a typom účastníka
 */
function spa_get_programs_for_city_dynamic($city_name) {
    global $wpdb;
    
    // KROK 1: Nájdi spa_place pre dané mesto
    $place_sql = $wpdb->prepare("
        SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'spa_place'
        AND p.post_status = 'publish'
        AND pm.meta_key = 'spa_place_city'
        AND pm.meta_value = %s
        LIMIT 1
    ", $city_name);
    
    $place_id = $wpdb->get_var($place_sql);
    
    // KROK 2: Ak spa_place má definované programy v postmeta
    $program_slugs = [];
    if ($place_id) {
        $programs_meta = get_post_meta($place_id, 'spa_place_programs', true);
        if (!empty($programs_meta) && is_array($programs_meta)) {
            $program_slugs = $programs_meta;
        }
    }
    
    // KROK 3: Načítaj spa_group programy
    $args = [
        'post_type' => 'spa_group',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ];
    
    // Ak existujú definované slugy, filtruj podľa nich
    if (!empty($program_slugs)) {
        $args['post_name__in'] = $program_slugs;
    }
    
    $query = new WP_Query($args);
    
    $programs = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            
            $post_id = get_the_ID();
            $age_from = get_post_meta($post_id, 'spa_age_from', true);
            $age_to = get_post_meta($post_id, 'spa_age_to', true);
            
            // Určenie typu účastníka
            $target = spa_determine_participant_type($age_from, $age_to);
            
            // Vytvorenie labelu
            $label = spa_format_program_label(get_the_title(), $age_from, $age_to, $target);
            
            $programs[] = [
                'id' => get_post_field('post_name'),
                'label' => $label,
                'target' => $target,
                'age_min' => $age_from ? intval($age_from) : null,
                'age_max' => $age_to ? intval($age_to) : null,
            ];
        }
        wp_reset_postdata();
    }
    
    // Zoradenie programov podľa age_from (vzostupne)
    usort($programs, function($a, $b) {
        $age_a = $a['age_min'] !== null ? $a['age_min'] : 999;
        $age_b = $b['age_min'] !== null ? $b['age_min'] : 999;
        return $age_a - $age_b;
    });
    
    return $programs;
}

/**
 * Helper: Určenie typu účastníka na základe veku
 * 
 * ŠPORTOVÁ KATEGORIZÁCIA:
 * 1. DETI: age_to <= 11 ALEBO (age_from < 12 && age_to IS NULL)
 * 2. MLÁDEŽ: age_from >= 12 && age_to <= 17
 * 3. DOSPELÍ: age_from >= 18
 * 
 * @param int|string $age_from Minimálny vek
 * @param int|string $age_to Maximálny vek
 * @return string 'child' | 'youth' | 'adult'
 */
function spa_determine_participant_type($age_from, $age_to) {
    $age_from = intval($age_from);
    $age_to = !empty($age_to) ? intval($age_to) : null;
    
    // PRAVIDLO 1: DETI
    // - age_to <= 11
    // - ALEBO age_from < 12 && age_to IS NULL (napr. 8+, 10+)
    if ($age_to !== null && $age_to <= 11) {
        return 'child';
    }
    if ($age_from < 12 && $age_to === null) {
        return 'child';
    }
    
    // PRAVIDLO 2: MLÁDEŽ
    // - age_from >= 12 && age_to <= 17
    if ($age_from >= 12 && $age_to !== null && $age_to <= 17) {
        return 'youth';
    }
    
    // PRAVIDLO 3: DOSPELÍ
    // - age_from >= 18
    if ($age_from >= 18) {
        return 'adult';
    }
    
    // Fallback (nemalo by sa stať)
    return 'child';
}

/**
 * Helper: Formátovanie labelu programu
 * 
 * FORMÁTY:
 * - DETI: "pre deti X–Y r. / Názov" alebo "pre deti X+ r. / Názov"
 * - MLÁDEŽ: "pre mládež X–Y r. / Názov"
 * - DOSPELÍ: "pre dospelých X+ r. / Názov"
 * 
 * @param string $title Názov programu
 * @param int|string $age_from Min vek
 * @param int|string $age_to Max vek
 * @param string $target Typ účastníka
 * @return string Formátovaný label
 */
function spa_format_program_label($title, $age_from, $age_to, $target) {
    // Konverzia na float a zachovanie desatinnej časti
    $age_from_float = !empty($age_from) ? floatval($age_from) : 0;
    $age_to_float = !empty($age_to) ? floatval($age_to) : null;
    
    $prefix = '';
    
    switch ($target) {
        case 'child':
            $prefix = 'pre deti';
            
            if ($age_from_float > 0 && $age_to_float !== null) {
                $age_from_str = spa_format_age_display($age_from_float);
                $age_to_str = spa_format_age_display($age_to_float);
                $prefix .= " {$age_from_str} - {$age_to_str} r.";
            } elseif ($age_from_float > 0) {
                $age_from_str = spa_format_age_display($age_from_float);
                $prefix .= " {$age_from_str}+ r.";
            }
            break;
            
        case 'youth':
            $prefix = 'pre mládež';
            
            if ($age_from_float > 0 && $age_to_float !== null) {
                $age_from_str = spa_format_age_display($age_from_float);
                $age_to_str = spa_format_age_display($age_to_float);
                $prefix .= " {$age_from_str} - {$age_to_str} r.";
            } elseif ($age_from_float > 0) {
                $age_from_str = spa_format_age_display($age_from_float);
                $prefix .= " {$age_from_str}+ r.";
            }
            break;
            
        case 'adult':
            $prefix = 'pre dospelých';
            
            if ($age_from_float > 0) {
                $age_from_str = spa_format_age_display($age_from_float);
                $prefix .= " {$age_from_str}+ r.";
            }
            break;
    }
    
    return $prefix . ' / ' . $title;
}

function spa_format_age_display($age) {
    // Ak je celé číslo (napr. 3.0), zobraz bez desatinnej časti
    if (fmod($age, 1) == 0) {
        return intval($age);
    }
    
    // Inak zobraz s čiarkou namiesto bodky
    return str_replace('.', ',', number_format($age, 1, '.', ''));
}