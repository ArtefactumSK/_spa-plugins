<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('spa_trainer')) {
    echo 'Nemáte oprávnenie.';
    return;
}

$schedule_id = isset($_GET['schedule_id']) ? (int) $_GET['schedule_id'] : 0;
$date = date('Y-m-d');

if (!$schedule_id) {
    echo 'Chýba rozvrh.';
    return;
}

global $wpdb;

$registrations = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT r.id, p.post_title
         FROM {$wpdb->prefix}spa_registrations r
         JOIN {$wpdb->posts} p ON p.ID = r.child_id
         WHERE r.schedule_id = %d",
        $schedule_id
    )
);
?>

<h2>Dochádzka – <?php echo esc_html($date); ?></h2>

<form method="post">
    <input type="hidden" name="schedule_id" value="<?php echo $schedule_id; ?>">
    <input type="hidden" name="date" value="<?php echo $date; ?>">

    <?php foreach ($registrations as $r): ?>
        <label>
            <input type="checkbox" name="attended[<?php echo $r->id; ?>]" value="1">
            <?php echo esc_html($r->post_title); ?>
        </label><br>
    <?php endforeach; ?>

    <button type="submit">Uložiť dochádzku</button>
</form>
