<?php
/**
 * SPA Feature Lock – UX helper
 *
 * Zobrazuje read-only stav rozšírených funkcií
 *
 * @package SPA Core
 */

if (!defined('ABSPATH')) exit;

function spa_feature_lock_notice(string $feature_key, string $label = '') {

    if (spa_feature_enabled($feature_key)) {
        return;
    }

    $text = $label ?: 'Táto funkcia je dostupná v rozšírenej verzii systému.';

    echo '<div class="spa-feature-lock">';
    echo '<span class="spa-lock-icon">🔒</span> ';
    echo esc_html($text);
    echo '</div>';
}
