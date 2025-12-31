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
    
    // Získaj obsah z WP stránky
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
    
    $content = $page->post_content;
    
    // Aplikuj WordPress shortcody a formátovanie
    $content = apply_filters('the_content', $content);
    
    // Extrahuj obsah pre daný stav
    $state_content = spa_extract_state_content($content, $state);
    
    // Nahraď placeholdery
    $state_content = spa_replace_placeholders($state_content, [
        'city_name' => $city_name,
        'program_name' => $program_name,
        'program_age' => $program_age,
    ]);
    
    // Pridaj ikony
    $icons = spa_get_infobox_icons($state);
    
    wp_send_json_success([
        'content' => $state_content,
        'icons' => $icons,
    ]);
}

/**
 * Extrakcia obsahu pre daný stav
 * 
 * @param string $content Celý obsah stránky
 * @param int $state Číslo stavu (0, 1, 2)
 * @return string Obsah pre daný stav
 */
function spa_extract_state_content($content, $state) {
    $pattern = '/<div class="spa-infobox-state-' . $state . '">(.*?)<\/div>/s';
    
    if (preg_match($pattern, $content, $matches)) {
        return $matches[1];
    }
    
    return '<p>Obsah pre tento stav nebol nájdený.</p>';
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
    
    /* switch ($state) {
        case 0:
            $icons['main'] = spa_icon('location', 'spa-icon-location', $icon_options);
            break;
        case 1:
            $icons['location'] = spa_icon('location', 'spa-icon-location', $icon_options);
            $icons['program'] = spa_icon('program', 'spa-icon-program', $icon_options);
            break;
        case 2:
            $icons['location'] = spa_icon('location', 'spa-icon-location', $icon_options);
            $icons['program'] = spa_icon('program', 'spa-icon-program', $icon_options);
            $icons['time'] = spa_icon('time', 'spa-icon-time', $icon_options);
            break;
    } */
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
            $options = ['stroke' => 'var(--theme-palette-color-2)']; // Zelená - všetko vybrané
            $icons['location'] = spa_icon('location', 'spa-icon-location', $options);
            $icons['program'] = spa_icon('program', 'spa-icon-program', $options);
            $icons['time'] = spa_icon('time', 'spa-icon-time', $options);
            break;
    }
    
    return $icons;
}