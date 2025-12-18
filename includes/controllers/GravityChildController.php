<?php
/**
 * Gravity Forms – Create SPA Child
 *
 * Vytvorí CPT spa_child a priradí ho k rodičovi
 *
 * @package SPA Core
 */

if (!defined('ABSPATH')) exit;

add_action('gform_after_submission', function ($entry, $form) {

    // ← ID FORMULÁRA REGISTRÁCIA DIEŤAŤA
    if ((int) $form['id'] !== 4) {
        return;
    }

    if (!is_user_logged_in()) {
        return;
    }

    $parent_id = get_current_user_id();
    $first_name = rgar($entry, '1.3');
    $last_name  = rgar($entry, '1.6');

    $child_name = trim($first_name . ' ' . $last_name);


    if (!$child_name) {
        return;
    }

    $child_id = wp_insert_post([
        'post_type'   => 'spa_child',
        'post_title'  => sanitize_text_field($child_name),
        'post_status' => 'publish',
    ]);

    if (is_wp_error($child_id)) {
        return;
    }

    // Väzba dieťa → rodič
    update_post_meta($child_id, '_spa_parent_id', $parent_id);

}, 10, 2);
