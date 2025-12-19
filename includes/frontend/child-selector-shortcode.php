<?php
/**
 * Shortcode: SPA Child Selector
 *
 * Zobrazuje výber dieťaťa pre prihláseného rodiča
 *
 * Použitie:
 * [spa_child_selector]
 *
 * @package SPA Core
 */

if (!defined('ABSPATH')) exit;

add_shortcode('spa_child_selector', function () {

    if (!is_user_logged_in()) {
        return '<p>Pre registráciu sa prosím prihláste.</p>';
    }

    $current_user = wp_get_current_user();
    $parent_id = (int) $current_user->ID;

    global $wpdb;
    $table = $wpdb->prefix . 'spa_children';

    // DEBUG: Over, aký user je prihlásený a aké deti existujú
    error_log('[SPA CHILD SELECTOR] Current user ID: ' . $parent_id);
    error_log('[SPA CHILD SELECTOR] User roles: ' . implode(', ', $current_user->roles));
    
    // Najprv over všetky deti v DB
    $all_children = $wpdb->get_results("SELECT id, parent_id, name FROM {$table} ORDER BY id DESC LIMIT 10");
    error_log('[SPA CHILD SELECTOR] All children in DB: ' . print_r($all_children, true));

    $children = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, name FROM $table WHERE parent_id = %d ORDER BY name",
            $parent_id
        )
    );
    
    error_log('[SPA CHILD SELECTOR] Children for parent_id=' . $parent_id . ': ' . count($children));

    if (!$children) {
        return '<p>Zatiaľ nemáte pridané žiadne dieťa.</p>';
    }

    ob_start();

    echo '<h3>Vyber dieťa</h3>';
    echo '<div class="spa-children">';

    foreach ($children as $child) {
        echo '<button type="button"
            class="spa-child-btn"
            data-child-id="' . esc_attr($child->id) . '"
            data-parent-id="' . esc_attr($parent_id) . '">
            ' . esc_html($child->name) . '
        </button>';
    }

    echo '</div>';

    // Feedback div (vyplní ho JavaScript)
    echo '<div class="spa-child-feedback"></div>';

    return ob_get_clean();
});