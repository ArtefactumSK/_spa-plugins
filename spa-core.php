<?php
/**
 * Plugin Name: SPA Core
 * Description: Core systém pre Samuel Piasecky ACADEMY (DB, logika, integrácie).
 * Version: 1.0.0
 */
if (!defined('WP_ACTIVATING_PLUGIN')) {
    define('WP_ACTIVATING_PLUGIN', false);
}


if (!defined('ABSPATH')) {
    exit;
}


// SAFE – vždy
require_once __DIR__ . '/includes/core/feature-flags.php';

// Ak NIE SME v aktivácii, načítaj zvyšok
if (!defined('WP_ACTIVATING_PLUGIN')) {

    require_once __DIR__ . '/includes/controllers/GravityRegistrationController.php';
    require_once __DIR__ . '/includes/controllers/AttendanceController.php';
    require_once __DIR__ . '/includes/controllers/GravityRegistrationController.php';

    require_once __DIR__ . '/includes/frontend/registrations-list.php';
    require_once __DIR__ . '/includes/frontend/attendance-shortcode.php';
    require_once __DIR__ . '/includes/frontend/shortcodes.php';
    require_once __DIR__ . '/includes/cpt/cpt-schedule.php';
    require_once __DIR__ . '/includes/services/AttendanceService.php';

    // DB install
    require_once __DIR__ . '/includes/db/install.php';

    // Services
    require_once __DIR__ . '/includes/core/feature-flags.php';
    register_activation_hook(__FILE__, 'spa_init_feature_flags');

    require_once __DIR__ . '/includes/services/RegistrationService.php';
    if (!class_exists('SPA_Registration_Service')) {
        error_log('[SPA CORE] RegistrationService class NOT loaded');
    }
    require_once __DIR__ . '/includes/cpt/cpt-registration.php';
    require_once __DIR__ . '/includes/roles/roles.php';



    // Taxonomies
    require_once __DIR__ . '/includes/taxonomies/tax-city.php';
    // CPT
    require_once __DIR__ . '/includes/cpt/cpt-venue.php';

    require_once __DIR__ . '/includes/frontend/schedules-shortcode.php';
    require_once __DIR__ . '/includes/frontend/child-selector-shortcode.php';

}



register_activation_hook(__FILE__, function () {
    spa_core_install_db();
    spa_core_register_roles();
    define('WP_ACTIVATING_PLUGIN', true);
    spa_init_feature_flags();
});

add_action('after_setup_theme', function () {
    if (!is_user_logged_in()) return;

    $user = wp_get_current_user();

    if (in_array('spa_trainer', (array) $user->roles, true)
        || in_array('spa_parent', (array) $user->roles, true)
        || in_array('spa_child', (array) $user->roles, true)
    ) {
        show_admin_bar(false);
    }
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'spa-registration',
        plugin_dir_url(__FILE__) . 'assets/js/registration.js',
        [],
        '1.0',
        true
    );
});

add_action('init', function () {
    error_log('FEATURE attendance_stats: ' . (spa_feature_enabled('attendance_stats') ? 'ON' : 'OFF'));
});

register_shutdown_function(function () {
    $out = ob_get_clean();
    if (!empty($out)) {
        error_log('[SPA OUTPUT BUFFER] >>>' . bin2hex($out) . '<<<');
    }
});
