<?php
/**
 * SPA Trial Info Panel
 *
 * Zobrazuje informačný panel pre manažéra
 */

if (!defined('ABSPATH')) exit;

function spa_render_trial_info_panel() {

    if (!current_user_can('manage_options')) {
        return;
    }

    $trial = spa_get_trial_status();

    echo '<div class="spa-trial-panel">';

    if ($trial['active']) {

        echo '<strong>Režim:</strong> Rozšírená verzia (TRIAL)<br>';
        echo '<strong>Platnosť do:</strong> ' . esc_html($trial['expires']);

    } else {

        echo '<strong>Režim:</strong> Základná verzia (CORE)<br>';
        echo '<span class="spa-trial-note">Niektoré rozšírené funkcie sú dostupné len v rozšírenej verzii.</span>';
    }

    echo '</div>';
}

add_shortcode('spa_trial_info', function () {
    ob_start();
    spa_render_trial_info_panel();
    return ob_get_clean();
});
