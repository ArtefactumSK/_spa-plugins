<?php
/**
 * SPA Core – Registration Service
 *
 * Zodpovedá výhradne za prácu s DB tabuľkami
 * týkajúcimi sa registrácií.
 */


if (!defined('ABSPATH')) {
    exit;
}

class SPA_Registration_Service {

    /**
     * Vytvorí registráciu v DB
     *
     * @param array $data
     * @return int|\WP_Error
     */
    public static function create(array $data) {
        global $wpdb;

        // Povinné polia
        $required = [
            'parent_id',
            'child_id',
            'program_id',
        ];

        foreach ($required as $key) {
            if (empty($data[$key])) {
                return new WP_Error(
                    'spa_registration_missing_field',
                    'Missing required field: ' . $key
                );
            }
        }

        $table = $wpdb->prefix . 'spa_registrations';

        $result = $wpdb->insert(
            $table,
            [
                'parent_id'  => (int) $data['parent_id'],
                'child_id'   => (int) $data['child_id'],
                'program_id'=> (int) $data['program_id'],
                'status'     => $data['status'] ?? 'pending',
                'created_at' => current_time('mysql'),
            ],
            [
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
            ]
        );

        if ($result === false) {
            return new WP_Error(
                'spa_registration_db_error',
                $wpdb->last_error
            );
        }

        return (int) $wpdb->insert_id;
    }
}