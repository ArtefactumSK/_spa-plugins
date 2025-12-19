<?php
/**
 * Gravity Forms → SPA Registration Controller (BULK MODE)
 * 
 * Spracuje registráciu jedného ALEBO viacerých detí naraz
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {

    // Gravity Forms ešte nemusí byť načítaný
    if (!class_exists('GFForms')) {
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

    // GF field IDs
    $program_id  = (int) rgar($entry, '27'); // ID Programu
    $schedule_id = (int) rgar($entry, '28'); // ID Rozvrhu
    $child_ids_raw = rgar($entry, '26'); // Child IDs (môže byť "1,2,3")

    error_log('[SPA] GF values program_id=' . $program_id . ' schedule_id=' . $schedule_id . ' child_ids=' . $child_ids_raw);

    // Rozdeľ child_ids (ak je viac oddelených čiarkou)
    $child_ids = array_filter(array_map('intval', explode(',', $child_ids_raw)));

    if (empty($child_ids)) {
        error_log('[SPA] Missing child_ids in GF entry');
        return;
    }

    $parent_id = get_current_user_id();

    // Pre každé dieťa vytvor samostatnú registráciu
    $results = [
        'success' => [],
        'error' => [],
    ];

    foreach ($child_ids as $child_id) {
        error_log('[SPA] Processing child_id=' . $child_id);

        $result = SPA_Registration_Service::create([
            'parent_id'   => $parent_id,
            'child_id'    => $child_id,
            'program_id'  => $program_id,
            'schedule_id' => $schedule_id,
        ]);

        if (is_wp_error($result)) {
            $results['error'][] = [
                'child_id' => $child_id,
                'message' => $result->get_error_message(),
            ];
            error_log('[SPA] create() error for child_id=' . $child_id . ': ' . $result->get_error_message());
        } else {
            $results['success'][] = [
                'child_id' => $child_id,
                'registration_id' => (int) $result,
            ];
            error_log('[SPA] created registration_id=' . (int) $result . ' for child_id=' . $child_id);
        }
    }

    // Sumár do logu
    error_log('[SPA BULK] Total: ' . count($child_ids) . ' | Success: ' . count($results['success']) . ' | Error: ' . count($results['error']));

    // Voliteľne: ulož výsledok do GF entry meta pre spätnú väzbu
    if (!empty($results['error'])) {
        gform_update_meta($entry['id'], 'spa_bulk_errors', json_encode($results['error']));
    }
}