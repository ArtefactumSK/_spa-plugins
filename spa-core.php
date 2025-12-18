<?php
/**
 * Plugin Name: SPA Core
 * Description: Core systém pre Samuel Piasecky ACADEMY (DB, logika, integrácie).
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// === CORE (bez side-effects) ===
require_once __DIR__ . '/includes/core/feature-flags.php';

// === DB / services ===
require_once __DIR__ . '/includes/db/schema.php';
require_once __DIR__ . '/includes/db/install.php';

// === Controllers ===
require_once __DIR__ . '/includes/controllers/GravityRegistrationController.php';
require_once __DIR__ . '/includes/controllers/GravityChildController.php';
require_once __DIR__ . '/includes/controllers/AttendanceController.php';

// === Frontend (shortcodes, view logic) ===
require_once __DIR__ . '/includes/frontend/registrations-list.php';


require_once __DIR__ . '/includes/services/RegistrationService.php';
if (!class_exists('SPA_Registration_Service')) {
    error_log('[SPA CORE] RegistrationService class NOT loaded');
}

require_once __DIR__ . '/includes/roles/roles.php';
require_once __DIR__ . '/includes/frontend/registrations-list.php';
require_once __DIR__ . '/includes/frontend/attendance-shortcode.php';
require_once __DIR__ . '/includes/frontend/shortcodes.php';
require_once __DIR__ . '/includes/cpt/cpt-schedule.php';
require_once __DIR__ . '/includes/services/AttendanceService.php';

require_once __DIR__ . '/includes/controllers/GravityRegistrationController.php';
require_once __DIR__ . '/includes/controllers/AttendanceController.php';
require_once __DIR__ . '/includes/controllers/GravityChildController.php';


// Taxonomies
require_once __DIR__ . '/includes/taxonomies/tax-city.php';
// CPT
require_once __DIR__ . '/includes/cpt/cpt-venue.php';

require_once __DIR__ . '/includes/frontend/schedules-shortcode.php';
require_once __DIR__ . '/includes/frontend/child-selector-shortcode.php';


register_activation_hook(__FILE__, function () {
    spa_core_install_db();
    spa_core_register_roles();
    register_activation_hook(__FILE__, 'spa_init_feature_flags');
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