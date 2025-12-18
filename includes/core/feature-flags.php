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

    $existing = get_option('spa_features');
    if ($existing && !empty($existing['trial_ends_at']) && current_time('Y-m-d') <= $existing['trial_ends_at']) {
        return;
    }

    // Inak vždy regeneruj
    delete_option('spa_features');

    $trial_start = current_time('Y-m-d');
    $trial_end   = date('Y-m-d', strtotime('+30 days'));

    $features = [
        'trial_active'     => true,
        'trial_started_at' => $trial_start,
        'trial_ends_at'    => $trial_end,

        'features' => [
            'attendance_stats'         => false,
            'payments_extended'        => 'extended',
            'messaging_extended'       => 'extended',
            'coach_dashboard_extended' => 'extended',
            'reports_extended'         => 'extended',
            'gps_verification'         => 'extended',
        ]
    ];

    // ✅ OPRAVA: Vynúť uloženie do DB (nie len do cache)
    add_option('spa_features', $features, '', 'yes');
    
    // Očisti cache aby sa nové dáta čítali
    wp_cache_delete('spa_features');
    
    error_log('[SPA INIT] Option stored to DB with add_option');
    error_log('[SPA INIT] Stored value: ' . json_encode($features, JSON_PRETTY_PRINT));
}


/**
 * Overí, či je rozšírená funkcionalita dostupná
 *
 * @param string $feature_key
 * @return bool
 */
function spa_feature_enabled(string $feature_key): bool {

    $options = get_option('spa_features');

    // Ak options neexistujú → default false
    if (!$options || empty($options['features'])) {
        return false;
    }

    // Ak kľúč neexistuje → default false
    if (!isset($options['features'][$feature_key])) {
        return false;
    }

    $feature_value = $options['features'][$feature_key];

    // EXPLICITNE FALSE alebo 0 → VRAŤ FALSE
    if ($feature_value === false || $feature_value === 0 || $feature_value === '0') {
        return false;
    }

    // Prázdne hodnoty → FALSE
    if (empty($feature_value)) {
        return false;
    }

    // Hodnota 'trial' → Skontroluj trial status
    if ($feature_value === 'trial') {
        if (empty($options['trial_active'])) {
            return false;
        }

        $today = current_time('Y-m-d');
        if (!empty($options['trial_ends_at']) && $today > $options['trial_ends_at']) {
            return false;
        }

        return true;
    }

    // Hodnota 'extended' → Skontroluj trial status
    if ($feature_value === 'extended') {
        if (empty($options['trial_active'])) {
            return false;
        }

        $today = current_time('Y-m-d');
        if (!empty($options['trial_ends_at']) && $today > $options['trial_ends_at']) {
            return false;
        }

        return true;
    }

    // Iné truthy hodnoty → TRUE
    return (bool) $feature_value;
}