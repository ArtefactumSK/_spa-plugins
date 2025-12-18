<?php
/**
 * SPA Trial Status Helper
 *
 * Poskytuje informácie o stave trial verzie
 */

if (!defined('ABSPATH')) exit;

function spa_get_trial_status(): array {

    $enabled = get_option('spa_trial_enabled', false);
    $expires = get_option('spa_trial_expires', null);

    $is_active = false;

    if ($enabled && $expires) {
        $is_active = (time() < strtotime($expires));
    }

    return [
        'enabled' => (bool) $enabled,
        'active'  => $is_active,
        'expires' => $expires,
    ];
}
