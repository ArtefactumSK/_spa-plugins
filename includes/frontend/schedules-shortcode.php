<?php
/**
 * Shortcode: SPA Schedules List
 *
 * Použitie:
 * [spa_schedules]
 *
 * @package SPA Core
 */

if (!defined('ABSPATH')) exit;

add_shortcode('spa_schedules', function ($atts) {
    $atts = shortcode_atts([
        'city' => '',
    ], $atts);

    if ($atts['city']) {
        $_GET['city'] = $atts['city'];
    }

    ob_start();
    include __DIR__ . '/schedules-list.php';
    return ob_get_clean();
});
