<?php
/**
 * CPT: SPA Venue (Miesto / Lokalita)
 *
 * Reprezentuje fyzické miesto tréningu (hala, škola, telocvičňa)
 * Obsahuje adresu a GPS údaje
 *
 * @package SPA Core
 */

if (!defined('ABSPATH')) exit;

add_action('init', function () {

    register_post_type('spa_venue', [
        'label' => 'Miesta',
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-location-alt',
        'supports' => ['title'],
    ]);

});
