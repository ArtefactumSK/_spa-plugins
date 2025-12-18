<?php
/**
 * SPA Feature Flags & Trial logic
 *
 * Riadi CORE vs EXTENDED vrstvy funkcionality
 *
 * @package SPA Core
 */

if (!defined('ABSPATH')) exit;

/**
 * Inicializácia feature flags (iba ak neexistujú)
 */
function spa_init_feature_flags() {

    if (get_option('spa_features')) {
        return;
    }

    $trial_start = current_time('Y-m-d');
    $trial_end   = date('Y-m-d', strtotime('+30 days'));

    $features = [
        'trial_active'     => true,
        'trial_started_at' => $trial_start,
        'trial_ends_at'    => $trial_end,

        'features' => [
            'attendance_stats'         => 'extended',
            'payments_extended'        => 'extended',
            'messaging_extended'       => 'extended',
            'coach_dashboard_extended' => 'extended',
            'reports_extended'         => 'extended',
            'gps_verification'         => 'extended',
        ]
    ];

    add_option('spa_features', $features);
}


/**
 * Overí, či je rozšírená funkcionalita dostupná
 *
 * @param string $feature_key
 * @return bool
 */
function spa_feature_enabled(string $feature_key): bool {

    $options = get_option('spa_features');

    if (!$options || empty($options['features'][$feature_key])) {
        return false;
    }

    if ($options['features'][$feature_key] !== 'extended') {
        return true;
    }

    if (empty($options['trial_active'])) {
        return false;
    }

    $today = current_time('Y-m-d');

    if (!empty($options['trial_ends_at']) && $today > $options['trial_ends_at']) {
        return false;
    }

    return true;
}
