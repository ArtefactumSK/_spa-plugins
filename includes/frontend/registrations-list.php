<?php
/**
 * SPA Core – Frontend Registrations List
 *
 * Zobrazuje registrácie podľa roly používateľa.
 */

if (!defined('ABSPATH')) exit;

function spa_get_registrations_for_current_user() {

    if (!is_user_logged_in()) {
        return [];
    }

    $user_id = get_current_user_id();

    // Základ WP_Query
    $args = [
        'post_type'      => 'spa_registration',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    /* ==========================
       ROLE LOGIKA
       ========================== */

    // Owner / Manager – všetko
    if (
        current_user_can('spa_view_all_registrations')
    ) {
        return new WP_Query($args);
    }

    // Parent – len svoje registrácie
    if (current_user_can('spa_view_own_registrations')) {

        // parent_id je uložený v DB, nie v CPT
        global $wpdb;
        $table = $wpdb->prefix . 'spa_registrations';

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE parent_id = %d",
                $user_id
            )
        );

        if (empty($ids)) {
            return [];
        }

        // CPT sú prepojené cez post_meta
        $args['meta_query'] = [
            [
                'key'     => '_spa_registration_id',
                'value'   => $ids,
                'compare' => 'IN',
            ],
        ];

        return new WP_Query($args);
    }

    // Iné roly zatiaľ nič
    return [];
}
