<?php
/**
 * SPA Core – CPT Schedule (Rozvrh)
 *
 * Jeden záznam = jeden tréningový termín programu
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {

    register_post_type('spa_schedule', [
        'labels' => [
            'name'          => 'Rozvrhy',
            'singular_name' => 'Rozvrh',
        ],
        'public'        => false,
        'show_ui'       => false,   // zatiaľ bez wp-admin UI
        'show_in_rest'  => true,
        'supports'      => ['title'],
        'capability_type' => 'post',
    ]);

});
