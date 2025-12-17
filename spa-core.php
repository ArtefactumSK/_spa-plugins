<?php
/**
 * Plugin Name: SPA Core
 * Description: Core systém pre Samuel Piasecky ACADEMY (DB, logika, integrácie).
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// DB install
require_once __DIR__ . '/includes/db/install.php';

// Services
require_once __DIR__ . '/includes/services/RegistrationService.php';
if (!class_exists('SPA_Registration_Service')) {
    error_log('[SPA CORE] RegistrationService class NOT loaded');
}
require_once __DIR__ . '/includes/controllers/GravityRegistrationController.php';
require_once __DIR__ . '/includes/cpt/cpt-registration.php';
require_once __DIR__ . '/includes/roles/roles.php';
require_once __DIR__ . '/includes/frontend/registrations-list.php';
require_once __DIR__ . '/includes/frontend/shortcodes.php';
require_once __DIR__ . '/includes/cpt/cpt-schedule.php';
require_once __DIR__ . '/includes/services/AttendanceService.php';
require_once __DIR__ . '/includes/controllers/AttendanceController.php';
require_once __DIR__ . '/includes/frontend/attendance-shortcode.php';
require_once __DIR__ . '/includes/taxonomies/tax-city.php';
// Taxonomies
require_once __DIR__ . '/includes/taxonomies/tax-city.php';
// CPT
require_once __DIR__ . '/includes/cpt/cpt-venue.php';


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