<?php
/**
 * Gravity Forms → SPA Registration Controller
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {

    // Gravity Forms ešte nemusí byť načítaný
    if (!class_exists('GFForms')) {
        $schedule_id = (int) rgar($entry, '28');
        return;
    }

    // REGISTRUJ HOOK AŽ TERAZ
    add_action('gform_after_submission_1', 'spa_handle_registration_form', 10, 2);
});

function spa_handle_registration_form($entry, $form) {

    if (!class_exists('SPA_Registration_Service')) {
        error_log('[SPA] RegistrationService class missing');
        return;
    }

    // GF field IDs (podľa tvojho screenshotu)
    $program_id  = (int) rgar($entry, '27'); // ID Programu = 49
    $schedule_id = (int) rgar($entry, '28'); // ID Rozvrhu = 898

    error_log('[SPA] GF values program_id=' . $program_id . ' schedule_id=' . $schedule_id);

    $result = SPA_Registration_Service::create([
        'parent_id'   => 1,
        'child_id'    => 1,
        'program_id'  => $program_id,
        'schedule_id' => $schedule_id,
    ]);

    if (is_wp_error($result)) {
        error_log('[SPA] create() error: ' . $result->get_error_message());
    } else {
        error_log('[SPA] created registration_id=' . (int) $result);
    }
}

