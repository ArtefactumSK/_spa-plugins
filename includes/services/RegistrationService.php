<?php
/**
 * SPA Core – Registration Service
 *
 * Zodpovedá za:
 * - zápis registrácie do DB (spa_registrations)
 * - vytvorenie CPT spa_registration ako vizuálnej reprezentácie
 *
 * DB = zdroj pravdy
 * CPT = UI vrstva
 */

if (!defined('ABSPATH')) {
    exit;
}

class SPA_Registration_Service {

    /**
     * Vytvorí registráciu:
     * 1. zapíše dáta do DB
     * 2. vytvorí CPT spa_registration
     * 3. prepojí CPT ↔ DB cez post_meta
     *
     * @param array $data
     * @return int|\WP_Error
     */


    /**    
    * Skontroluje kapacitu rozvrhu
    */
    private static function is_schedule_full($schedule_id) {
        global $wpdb;

        $capacity = (int) get_post_meta($schedule_id, '_spa_capacity', true);

        // Ak kapacita nie je nastavená → neobmedzujeme
        if ($capacity <= 0) {
            return false;
        }

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}spa_registrations WHERE schedule_id = %d",
                $schedule_id
            )
        );

        return $count >= $capacity;
    }

    public static function create(array $data) {
        global $wpdb;

        // Povinné polia
        $required = ['parent_id', 'child_id', 'program_id'];

        foreach ($required as $key) {
            if (empty($data[$key])) {
                return new WP_Error(
                    'spa_registration_missing_field',
                    'Missing required field: ' . $key
                );
            }
        }

        $table = $wpdb->prefix . 'spa_registrations';
        
        $schedule_id = isset($data['schedule_id']) ? (int) $data['schedule_id'] : null;

        if (!empty($data['schedule_id'])) {
            if (self::is_schedule_full((int) $data['schedule_id'])) {
                return new WP_Error(
                    'spa_capacity_full',
                    'Kapacita tréningu je naplnená.'
                );
            }
        }



        /* ==========================
           1. ZÁPIS DO DB
           ========================== */
        
        $result = $wpdb->insert(
            $table,
            [
                'parent_id'   => (int) $data['parent_id'],
                'child_id'    => (int) $data['child_id'],
                'program_id'  => (int) $data['program_id'],
                'schedule_id' => $schedule_id,
                'status'      => $data['status'] ?? 'pending',
                'created_at'  => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%d', '%s', '%s']
        );        

        if ($result === false) {
            return new WP_Error(
                'spa_registration_db_error',
                $wpdb->last_error
            );
        }

        $registration_id = (int) $wpdb->insert_id;

        /* ==========================
           2. VYTVORENIE CPT
           ========================== */

        $post_id = wp_insert_post([
            'post_type'   => 'spa_registration',
            'post_title'  => 'Registrácia #' . $registration_id,
            'post_status' => 'publish',
        ]);

        if (!is_wp_error($post_id)) {
            // prepojenie CPT → DB
            update_post_meta(
                $post_id,
                '_spa_registration_id',
                $registration_id
            );
        }

        /* ==========================
           3. VÝSLEDOK
           ========================== */

        return $registration_id;
    }
}
