    <?php
if (!defined('ABSPATH')) exit;

add_action('init', function () {

    if (!current_user_can('spa_trainer')) return;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (empty($_POST['schedule_id']) || empty($_POST['date'])) return;

    $schedule_id = (int) $_POST['schedule_id'];
    $date = sanitize_text_field($_POST['date']);
    $attended = $_POST['attended'] ?? [];

    foreach ($attended as $registration_id => $val) {
        SPA_Attendance_Service::set_attendance(
            $schedule_id,
            (int) $registration_id,
            $date,
            true
        );
    }

    wp_redirect(add_query_arg('saved', 1));
    exit;
});
