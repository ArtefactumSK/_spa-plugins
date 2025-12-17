<?php
/**
 * Taxonomy: SPA City
 *
 * Slúži na priradenie programov a rozvrhov ku konkrétnemu mestu
 *
 * Použitie:
 * - spa_program
 * - spa_schedule
 *
 * @package SPA Core
 */

if (!defined('ABSPATH')) exit;

add_action('init', function () {

    register_taxonomy(
        'spa_city',
        ['spa_program', 'spa_schedule'],
        [
            'label'             => 'Mestá',
            'public'            => true,
            'hierarchical'      => false,
            'show_admin_column' => true,
            'rewrite'           => [
                'slug' => 'mesto'
            ],
        ]
    );

});
