<?php
/**
 * Gravity Forms → SPA Registration Controller
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('gform_after_submission', 'spa_handle_registration_form', 10, 2);

function spa_handle_registration_form($entry, $form) {
    error_log('SPA GF controller RUNNING');

    // ⛔ ZMENÍŠ na ID svojho registračného formulára
    $REG_FORM_ID = 1;

    /* if ((int) $form['id'] !== $REG_FORM_ID) {
        return;
    } */

    if (!class_exists('SPA_Registration_Service')) {
        error_log('[SPA CORE] RegistrationService missing');
        return;
    }

    $data = [
    'parent_id'  => 1,
    'child_id'   => 1,
    'program_id' => 1,
    ];


    /* $data = [
        'parent_id'  => rgar($entry, '1'), // ID poľa v GF
        'child_id'   => rgar($entry, '2'),
        'program_id' => rgar($entry, '3'),
    ]; */

    $result = SPA_Registration_Service::create($data);

    if (is_wp_error($result)) {
        error_log('[SPA CORE] Registration failed: ' . $result->get_error_message());
    }
}
