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

    $children = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, name FROM $table WHERE parent_id = %d ORDER BY name",
            $parent_id
        )
    );

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

    return ob_get_clean();
});