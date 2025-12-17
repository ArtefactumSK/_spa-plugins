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

    // Trainer – len registrácie jeho programov
        if (in_array('spa_trainer', (array) wp_get_current_user()->roles, true)) {

            global $wpdb;
            $user_id = get_current_user_id();

            // 1. Programy trénera (CPT spa_group)
            $program_ids = get_posts([
                'post_type'   => 'spa_group',
                'numberposts' => -1,
                'fields'      => 'ids',
                'meta_query'  => [
                    [
                        'key'   => '_spa_trainer_user_id',
                        'value' => $user_id,
                    ],
                ],
            ]);

            if (empty($program_ids)) {
                return [];
            }

            // 2. Registrácie v DB
            $table = $wpdb->prefix . 'spa_registrations';

            $registration_ids = $wpdb->get_col(
                "SELECT id FROM $table WHERE program_id IN (" . implode(',', array_map('intval', $program_ids)) . ")"
            );

            if (empty($registration_ids)) {
                return [];
            }

            // 3. Nájsť CPT registrácie podľa meta
            return new WP_Query([
                'post_type'  => 'spa_registration',
                'meta_query' => [
                    [
                        'key'     => '_spa_registration_id',
                        'value'   => $registration_ids,
                        'compare' => 'IN',
                    ],
                ],
                'orderby' => 'date',
                'order'   => 'DESC',
            ]);
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
