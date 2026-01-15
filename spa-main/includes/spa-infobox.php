<?php
/**
 * SPA Infobox Wizard
 * Dynamický infobox pre Gravity Forms wizard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializácia infobox modulu
 */
function spa_infobox_init() {
    // Hook na aktiváciu pluginu
    register_activation_hook(SPA_PLUGIN_FILE, 'spa_infobox_setup_page');
    
    // AJAX endpoint pre načítanie infobox obsahu
    add_action('wp_ajax_spa_get_infobox_content', 'spa_ajax_get_infobox_content');
    add_action('wp_ajax_nopriv_spa_get_infobox_content', 'spa_ajax_get_infobox_content');
}

/**
 * Setup SPA Infobox stránky pri aktivácii pluginu
 */
function spa_infobox_setup_page() {
    $page_id = spa_get_infobox_page_id();
    
    // Ak stránka neexistuje, vytvor ju
    if (!$page_id) {
        $page_id = spa_create_infobox_page();
        
        if ($page_id) {
            // Označ stránku ako systémovú
            update_post_meta($page_id, '_spa_system_page', 'infobox_wizard');
            
            // Ulož Page ID do options
            update_option('spa_infobox_page_id', $page_id);
            
            spa_log('SPA Infobox page created', ['page_id' => $page_id]);
        }
    } else {
        // Stránka existuje, len aktualizuj meta
        update_post_meta($page_id, '_spa_system_page', 'infobox_wizard');
        update_option('spa_infobox_page_id', $page_id);
    }
}

/**
 * Získanie Page ID infobox stránky
 * 
 * Priorita:
 * 1. Z options (ak už bola detekovaná)
 * 2. Hľadaj stránku s meta flagom
 * 3. Fallback hľadanie podľa slugu
 * 
 * @return int|false Page ID alebo false
 */
function spa_get_infobox_page_id() {
    // 1. Z options
    $page_id = get_option('spa_infobox_page_id');
    if ($page_id && get_post_status($page_id) === 'publish') {
        return intval($page_id);
    }
    
    // 2. Hľadaj podľa meta flagu
    $args = [
        'post_type' => 'page',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => '_spa_system_page',
                'value' => 'infobox_wizard',
            ],
        ],
    ];
    
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        $page = $query->posts[0];
        update_option('spa_infobox_page_id', $page->ID);
        return $page->ID;
    }
    
    // 3. Fallback hľadanie podľa slugu
    $page = get_page_by_path('spa-infobox-wizard');
    if ($page && $page->post_status === 'publish') {
        update_post_meta($page->ID, '_spa_system_page', 'infobox_wizard');
        update_option('spa_infobox_page_id', $page->ID);
        return $page->ID;
    }
    
    return false;
}

/**
 * Vytvorenie SPA Infobox stránky
 * 
 * @return int|false Page ID alebo false
 */
function spa_create_infobox_page() {
    $default_content = spa_get_default_infobox_content();
    
    $page_data = [
        'post_title' => 'SPA Infobox Wizard',
        'post_name' => 'spa-infobox-wizard',
        'post_content' => $default_content,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_author' => 1,
        'comment_status' => 'closed',
        'ping_status' => 'closed',
    ];
    
    $page_id = wp_insert_post($page_data);
    
    return $page_id ? $page_id : false;
}

/**
 * Predvolený obsah infobox stránky
 * 
 * @return string HTML obsah s placeholdermi
 */
function spa_get_default_infobox_content() {
    return <<<HTML
<div class="spa-infobox-state-0">
<h3>Vyberte mesto a program</h3>
<p>Začnite výberom mesta, v ktorom sa chcete zúčastniť tréningov.</p>
</div>

<div class="spa-infobox-state-1">
<h3>Mesto: {{city_name}}</h3>
<p>Teraz vyberte tréningový program, ktorý vás zaujíma.</p>
</div>

<div class="spa-infobox-state-2">
<h3>Váš výber</h3>
<p><strong>Mesto:</strong> {{city_name}}</p>
<p><strong>Program:</strong> {{program_name}}</p>
<p><strong>Vek:</strong> {{program_age}}</p>
<p>Pokračujte vyplnením kontaktných údajov.</p>
</div>
HTML;
}

/**
 * AJAX: Získanie obsahu infoboxu
 */
function spa_ajax_get_infobox_content() {
    $state = isset($_POST['state']) ? intval($_POST['state']) : 0;
    $city_name = isset($_POST['city_name']) ? sanitize_text_field($_POST['city_name']) : '';
    $program_name = isset($_POST['program_name']) ? sanitize_text_field($_POST['program_name']) : '';
    $program_age = isset($_POST['program_age']) ? sanitize_text_field($_POST['program_age']) : '';

    if (empty($program_age) && !empty($program_name)) {
        // Regex: zachytáva 1,8–3 alebo 6+ alebo 12-15
        if (preg_match('/(\d+(?:,\d+)?)\s*[–-]\s*(\d+(?:,\d+)?)/', $program_name, $matches)) {
            $program_age = $matches[1] . '–' . $matches[2];
        } elseif (preg_match('/(\d+(?:,\d+)?)\+/', $program_name, $matches)) {
            $program_age = $matches[1] . '+';
        }
    }
    
    spa_log('Infobox AJAX called', [
        'state' => $state,
        'city_name' => $city_name,
        'program_name' => $program_name,
        'program_age' => $program_age
    ]);

    $page_id = spa_get_infobox_page_id();
    
    if (!$page_id) {
        wp_send_json_error(['message' => 'Infobox stránka neexistuje.']);
        return;
    }
    
    $page = get_post($page_id);
    if (!$page) {
        wp_send_json_error(['message' => 'Chyba pri načítaní obsahu.']);
        return;
    }
    
    $content = apply_filters('the_content', $page->post_content);
    
    $content = spa_replace_placeholders($content, [
        'city_name' => $city_name,
        'program_name' => $program_name,
        'program_age' => $program_age,
    ]);
    
    $icons = spa_get_infobox_icons($state);    
    
    // Získaj program_id z POST
    $program_id_from_post = isset($_POST['program_id']) ? sanitize_text_field($_POST['program_id']) : '';

    global $wpdb;
    $program_id = null;

    // Hľadaj program (WordPress API detekuje správny prefix automaticky)
    if (!empty($program_id_from_post)) {
        if (is_numeric($program_id_from_post)) {
            $program_id = intval($program_id_from_post);
        } else {
            // Hľadaj podľa slug
            $program_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = 'spa_group' 
                    AND post_name = %s 
                    AND post_status = 'publish' 
                    LIMIT 1",
                    $program_id_from_post
                )
            );
        }
    }

    // Fallback: hľadaj podľa názvu + MESTO
    if (!$program_id && !empty($program_name)) {
        // Ak máme mesto, filtruj podľa neho
        if (!empty($city_name)) {
            // Nájdi place_id pre dané mesto
            $place_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT p.ID 
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type = 'spa_place'
                    AND p.post_status = 'publish'
                    AND pm.meta_key = 'spa_place_city'
                    AND pm.meta_value = %s",
                    $city_name
                )
            );
            
            spa_log('Place IDs for city', [
                'city_name' => $city_name,
                'place_ids' => $place_ids
            ]);
            
            if (!empty($place_ids)) {
                $place_ids_str = implode(',', array_map('intval', $place_ids));
                
                // Nájdi program v tomto mieste
                $program_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT p.ID 
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                        WHERE p.post_type = 'spa_group'
                        AND p.post_status = 'publish'
                        AND p.post_title = %s
                        AND pm.meta_key = 'spa_place_id'
                        AND pm.meta_value IN ({$place_ids_str})
                        LIMIT 1",
                        $program_name
                    )
                );
                
                spa_log('Program lookup WITH city filter', [
                    'program_name' => $program_name,
                    'city_name' => $city_name,
                    'place_ids' => $place_ids_str,
                    'found_program_id' => $program_id
                ]);
            } else {
                spa_log('No places found for city', ['city_name' => $city_name]);
            }
        } else {
            // Bez mesta - hľadaj globálne (pôvodná logika)
            $program_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = 'spa_group' 
                    AND post_title = %s 
                    AND post_status = 'publish' 
                    LIMIT 1",
                    $program_name
                )
            );
            
            spa_log('Program lookup WITHOUT city filter', [
                'program_name' => $program_name,
                'found_program_id' => $program_id
            ]);
        }
    }

    spa_log('Program ID lookup', [
        'table_prefix' => $wpdb->prefix,
        'posts_table' => $wpdb->posts,
        'program_id_from_post' => $program_id_from_post,
        'program_name' => $program_name,
        'found_program_id' => $program_id
    ]);

    // Inicializácia premenných (zabezpečenie proti undefined)
    $program_data = null;
    $place_data = null;
    $price_label = null;
    $capacity_free = null;

    // Načítaj údaje programu ak je vybraný
    if ($program_id) {
        $program_post = get_post($program_id);
        
        if ($program_post && $program_post->post_status === 'publish') {
            // Získaj ikonu programu
            $icon_name = get_post_meta($program_id, 'spa_icon', true);
            $icon_svg = '';
            
            if ($icon_name) {
                $icon_path = WP_CONTENT_DIR . '/uploads/spa-icons/' . $icon_name;
                if (file_exists($icon_path)) {
                    $icon_svg = file_get_contents($icon_path);
                }
            }
            
            // Získaj farby programu
            $primary_color = get_post_meta($program_id, 'spa_icon_primary_color', true);
            $secondary_color = get_post_meta($program_id, 'spa_icon_secondary_color', true);

            error_log('[SPA Infobox] Program ID: ' . $program_id);
            error_log('[SPA Infobox] Primary color: ' . $primary_color);
            error_log('[SPA Infobox] Secondary color: ' . $secondary_color);
            
            // Získaj mapping úrovne s emoji
            $level_raw = get_post_meta($program_id, 'spa_level', true);
            $level_labels = [
                'beginner' => '🟢 Začiatočník',
                'intermediate' => '🟡 Mierne pokročilý',
                'advanced' => '🟠 Pokročilý',
                'professional' => '🔴 Profesionál'
            ];
            $level_display = isset($level_labels[$level_raw]) ? $level_labels[$level_raw] : $level_raw;
            
            // Pridaj úroveň do contentu
            $program_content = apply_filters('the_content', $program_post->post_content);
            if (!empty($level_display)) {
                $program_content .= '<p style="margin-bottom: 0px;"><strong>Úroveň:</strong> ' . esc_html($level_display) . '</p>';
            }
            
            $program_data = [
                'title' => $program_post->post_title,
                'content' => $program_content,
                'icon' => $icon_svg,
                'primary_color' => !empty($primary_color) ? $primary_color : '#6d71b2',
                'secondary_color' => !empty($secondary_color) ? $secondary_color : '#000000',
                'age_min' => get_post_meta($program_id, 'spa_age_from', true),
                'age_max' => get_post_meta($program_id, 'spa_age_to', true),
                'spa_level' => get_post_meta($program_id, 'spa_level', true),
                'spa_price_1x_weekly' => get_post_meta($program_id, 'spa_price_1x_weekly', true),
                'spa_price_2x_weekly' => get_post_meta($program_id, 'spa_price_2x_weekly', true),
                'spa_price_monthly' => get_post_meta($program_id, 'spa_price_monthly', true),
                'spa_price_semester' => get_post_meta($program_id, 'spa_price_semester', true),
                'spa_external_surcharge' => get_post_meta($program_id, 'spa_external_surcharge', true),
            ];

            // Získaj údaje miesta
            $place_id = get_post_meta($program_id, 'spa_place_id', true);
            
            if ($place_id) {
                $place_post = get_post($place_id);
                
                if ($place_post && $place_post->post_status === 'publish') {
                    $place_data = [
                        'name' => $place_post->post_title,
                        'address' => get_post_meta($place_id, 'spa_place_address', true),
                        'city' => get_post_meta($place_id, 'spa_place_city', true),
                    ];
                }
            }

            // Výpočet kapacity
            $capacity_total = (int) get_post_meta($program_id, 'spa_capacity', true);
            
            if ($capacity_total <= 0) {
                $capacity_total = 100;
            }
        
            // Spočítaj aktívne registrácie (CPT)
            $registered_active = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT p.ID)
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'program_id'
                     INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'status'
                     WHERE p.post_type = 'spa_registration'
                     AND p.post_status = 'publish'
                     AND pm1.meta_value = %d
                     AND pm2.meta_value = 'active'",
                    $program_id
                )
            );
            
            spa_log('Registrations count (from CPT)', [
                'program_id' => $program_id,
                'registered_active' => $registered_active
            ]);
        
            // Vypočítaj voľnú kapacitu
            $capacity_free = max(0, $capacity_total - $registered_active);
            
            spa_log('Capacity calculated', [
                'program_id' => $program_id,
                'capacity_total' => $capacity_total,
                'registered_active' => $registered_active,
                'capacity_free' => $capacity_free
            ]);

            // Výpočet ceny
            $prices = [];

            // 1x týždenne
            $price_1x = get_post_meta($program_id, 'spa_price_1x_weekly', true);
            if ($price_1x && floatval($price_1x) > 0) {
                $prices[] = number_format((float)$price_1x, 0, ',', ' ') . ' € / 1× týždenne';
            }

            // 2x týždenne
            $price_2x = get_post_meta($program_id, 'spa_price_2x_weekly', true);
            if ($price_2x && floatval($price_2x) > 0) {
                $prices[] = number_format((float)$price_2x, 0, ',', ' ') . ' € / 2× týždenne';
            }

            // Mesačný paušál (fallback)
            if (empty($prices)) {
                $monthly = get_post_meta($program_id, 'spa_price_monthly', true);
                if ($monthly && floatval($monthly) > 0) {
                    $prices[] = number_format((float)$monthly, 0, ',', ' ') . ' € / mesiac';
                }
            }

            // Semester (fallback)
            if (empty($prices)) {
                $semester = get_post_meta($program_id, 'spa_price_semester', true);
                if ($semester && floatval($semester) > 0) {
                    $prices[] = number_format((float)$semester, 0, ',', ' ') . ' € / semester';
                }
            }

            // Výsledná cena pre infobox
            $price_label = !empty($prices) ? implode(' • ', $prices) : null;

            spa_log('Infobox price resolved', [
                'program_id' => $program_id,
                'price_1x'   => $price_1x,
                'price_2x'   => $price_2x,
                'final'      => $price_label
            ]);
        }
    } else {
        spa_log('Program ID not found', ['program_name' => $program_name]);
    }

    // DEBUG: Loguj PRESNE to, čo ide do JSON
    spa_log('AJAX Response PRED odoslaním', [
        'capacity_free' => $capacity_free,
        'program_id' => $program_id ?? 'NULL',
        'program_name' => $program_name,
        'state' => $state,
        'icons_keys' => array_keys($icons),
        'spa_logo_exists' => isset($icons['spa_logo']),
        'spa_logo_length' => isset($icons['spa_logo']) ? strlen($icons['spa_logo']) : 0,
        'spa_logo_preview' => isset($icons['spa_logo']) ? substr($icons['spa_logo'], 0, 100) : 'NONE'
    ]);

    error_log('[SPA Infobox PHP] program_id: ' . ($program_id ?? 'NULL'));
    error_log('[SPA Infobox PHP] program_data title: ' . ($program_data['title'] ?? 'NULL'));
    error_log('[SPA Infobox PHP] capacity_free: ' . ($capacity_free ?? 'NULL'));
    error_log('[SPA Infobox PHP] price_label: ' . ($price_label ?? 'NULL'));
    error_log('[SPA Infobox PHP] place_data name: ' . ($place_data['name'] ?? 'NULL'));
    error_log('[SPA Infobox PHP] place_data address: ' . ($place_data['address'] ?? 'NULL'));
    error_log('[SPA Infobox PHP] place_data city: ' . ($place_data['city'] ?? 'NULL'));
    error_log('[SPA Infobox PHP] icons keys: ' . implode(', ', array_keys($icons)));
    error_log('[SPA Infobox PHP] spa_logo exists: ' . (isset($icons['spa_logo']) ? 'YES' : 'NO'));
    error_log('[SPA Infobox PHP] spa_logo length: ' . (isset($icons['spa_logo']) ? strlen($icons['spa_logo']) : 0));
    error_log('[SPA Infobox PHP] spa_logo preview: ' . (isset($icons['spa_logo']) ? substr($icons['spa_logo'], 0, 100) : 'NONE'));

    wp_send_json_success([
        'content' => $content,
        'icons' => $icons,
        'capacity_free' => $capacity_free,
        'price' => $price_label,
        'program' => $program_data,
        'place' => $place_data,
    ]);
}

/**
 * Nahradenie placeholderov v obsahu
 * 
 * @param string $content Obsah s placeholdermi
 * @param array $data Dáta na nahradenie
 * @return string Obsah s nahradenými placeholdermi
 */
function spa_replace_placeholders($content, $data) {
    foreach ($data as $key => $value) {
        $placeholder = '{{' . $key . '}}';
        $content = str_replace($placeholder, esc_html($value), $content);
    }
    
    return $content;
}

/**
 * Získanie ikon pre daný stav
 * 
 * @param int $state Číslo stavu
 * @return array Pole SVG ikon
 */
function spa_get_infobox_icons($state) {
    $icons = [];
    
    // Definuj farby pre ikony
    $icon_options = [
        'fill' => 'none',
        'stroke' => '#0066cc', // Primárna farba SPA
    ];
    
    switch ($state) {
        case 0:
            $options = ['stroke' => '#cccccc']; // Sivá - nič nevybrané
            $icons['main'] = spa_icon('location', 'spa-icon-location', $options);
            break;
        case 1:
            $options = ['stroke' => 'var(--theme-palette-color-1)']; // Modrá - mesto vybrané
            $icons['location'] = spa_icon('location', 'spa-icon-location', $options);
            $icons['program'] = spa_icon('program', 'spa-icon-program', ['stroke' => '#cccccc']);
            break;
        case 2:
            $options = ['stroke' => 'var(--theme-palette-color-3)'];
            $icons['location'] = spa_icon('location', 'spa-icon-location', $options);
            $icons['spa_program'] = spa_icon('spa_program', 'spa-icon-spa_program', $options);
            $icons['price'] = spa_icon('price', 'spa-icon-price', ['fill' => 'var(--theme-palette-color-3)', 'stroke' => 'none']);
            $icons['time'] = spa_icon('time', 'spa-icon-time', $options);
            // Ikony pre summary
            $icons['age'] = spa_icon('age', 'spa-icon-age', $options);
            $icons['capacity'] = spa_icon('capacity', 'spa-icon-capacity', $options);
            
            // SPA logo (nad ikonou programu)
            $icons['spa_logo'] = spa_icon('spa-icon', 'spa-logo-small', $options);
            break;
    }
    
    return $icons;
}

/**
 * Admin nástroj: Manuálne nastavenie infobox page ID
 */
add_action('admin_init', function() {
    // Trigger: ?spa_set_infobox_page=730
    if (isset($_GET['spa_set_infobox_page']) && current_user_can('administrator')) {
        $page_id = intval($_GET['spa_set_infobox_page']);
        
        // Kontrola či stránka existuje
        $page = get_post($page_id);
        
        if (!$page || $page->post_status !== 'publish') {
            wp_die('Stránka s ID ' . $page_id . ' neexistuje alebo nie je publikovaná.');
        }
        
        // Ulož ID
        update_option('spa_infobox_page_id', $page_id);
        update_post_meta($page_id, '_spa_system_page', 'infobox_wizard');
        
        // Presmeruj na editáciu stránky
        wp_redirect(admin_url('post.php?post=' . $page_id . '&action=edit&message=updated'));
        exit;
    }
});