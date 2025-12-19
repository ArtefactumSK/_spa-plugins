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

require_once __DIR__ . '/includes/core/trial-status.php';

// === Controllers ===
require_once __DIR__ . '/includes/controllers/GravityRegistrationController.php';
require_once __DIR__ . '/includes/controllers/GravityChildController.php';
require_once __DIR__ . '/includes/controllers/AttendanceController.php';

// === Frontend (shortcodes, view logic) ===
require_once __DIR__ . '/includes/frontend/feature-lock.php';
require_once __DIR__ . '/includes/frontend/registrations-list.php';
require_once __DIR__ . '/includes/frontend/attendance-shortcode.php';
require_once __DIR__ . '/includes/frontend/shortcodes.php';
require_once __DIR__ . '/includes/frontend/schedules-shortcode.php';
require_once __DIR__ . '/includes/frontend/child-selector-shortcode.php';

require_once __DIR__ . '/includes/frontend/trial-info-panel.php';


require_once __DIR__ . '/includes/services/RegistrationService.php';
if (!class_exists('SPA_Registration_Service')) {
    error_log('[SPA CORE] RegistrationService class NOT loaded');
}

// roles
require_once __DIR__ . '/includes/roles/roles.php';

// Services
require_once __DIR__ . '/includes/services/AttendanceService.php';


// Taxonomies
require_once __DIR__ . '/includes/taxonomies/tax-city.php';

// CPT
require_once __DIR__ . '/includes/cpt/cpt-program.php';  // ← NOVÝ
require_once __DIR__ . '/includes/cpt/cpt-venue.php';
require_once __DIR__ . '/includes/cpt/cpt-schedule.php';



register_activation_hook(__FILE__, function () {
    spa_core_install_db();
    spa_core_register_roles();
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

// Test users (len pre adminov)
if (is_admin()) {
    $test_file = __DIR__ . '/includes/admin/create-test-users.php';
    
    if (file_exists($test_file)) {
        error_log('[SPA DEBUG] Test users file exists: ' . $test_file);
        require_once $test_file;
    } else {
        error_log('[SPA DEBUG] Test users file NOT FOUND: ' . $test_file);
    }
}

add_action('wp_enqueue_scripts', function () {
    // JS
    wp_enqueue_script('spa-registration', plugin_dir_url(__FILE__) . 'assets/js/registration.js', [], '1.0', true);
    wp_enqueue_script('spa-child-selector', plugin_dir_url(__FILE__) . 'assets/js/child-selector.js', [], '1.1', true);

    // CSS (NOVÝ)
    wp_enqueue_style('spa-child-selector', plugin_dir_url(__FILE__) . 'assets/css/child-selector.css', [], '1.0');
});

add_action('init', function () {
    error_log('FEATURE attendance_stats: ' . (spa_feature_enabled('attendance_stats') ? 'ON' : 'OFF'));
});