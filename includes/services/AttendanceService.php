<?php
if (!defined('ABSPATH')) exit;

class SPA_Attendance_Service {

    public static function set_attendance($schedule_id, $registration_id, $date, $attended) {
        global $wpdb;

        $table = $wpdb->prefix . 'spa_attendance';

        return $wpdb->replace(
            $table,
            [
                'schedule_id'     => (int) $schedule_id,
                'registration_id' => (int) $registration_id,
                'attendance_date' => $date,
                'attended'        => $attended ? 1 : 0,
            ],
            ['%d', '%d', '%s', '%d']
        );
    }

    public static function get_attendance($schedule_id, $date) {
        global $wpdb;

        $table = $wpdb->prefix . 'spa_attendance';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE schedule_id = %d AND attendance_date = %s",
                $schedule_id,
                $date
            )
        );
    }
}
