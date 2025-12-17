<?php
/**
 * SPA Core – Shortcodes
 */

if (!defined('ABSPATH')) exit;

add_shortcode('spa_registrations', function () {

    $query = spa_get_registrations_for_current_user();

    if (empty($query) || !$query->have_posts()) {
        return '<p>Žiadne registrácie.</p>';
    }

    ob_start();
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
