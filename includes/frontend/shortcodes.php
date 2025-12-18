<?php
/**
 * SPA Core – Shortcodes
 */

if (!defined('ABSPATH')) exit;

add_shortcode('spa_registrations', function () {

    ob_start(); // ⬅️ DÔLEŽITÉ

    /**
     * TRIAL gate – štatistiky dochádzky (UX info)
     * Zobrazí sa aj v prípade, že ešte nie sú žiadne registrácie
     */
    if (current_user_can('spa_trainer')) {
        if (!spa_feature_enabled('attendance_stats')) {
            spa_feature_lock_notice(
                'attendance_stats',
                'Štatistiky dochádzky (percentá, trendy) sú dostupné v rozšírenej verzii.'
            );
        }
    }

    // Získanie registrácií
    $query = spa_get_registrations_for_current_user();

    if (empty($query) || !$query->have_posts()) {
        echo '<p>Žiadne registrácie.</p>';
        return ob_get_clean(); // ⬅️ VRÁTIME AJ GATE
    }

    echo '<ul class="spa-registrations">';

    while ($query->have_posts()) {
        $query->the_post();

        $reg_id = get_post_meta(get_the_ID(), '_spa_registration_id', true);

        echo '<li>';
        echo '<strong>' . esc_html(get_the_title()) . '</strong>';
        echo '<br>ID registrácie: ' . esc_html($reg_id);
        echo '</li>';
    }

    echo '</ul>';

    wp_reset_postdata();

    return ob_get_clean();
});
