<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('spa_trainer')) {
    echo 'Nemáte oprávnenie.';
    return;
}

if (isset($_GET['saved'])): ?>
    <div style="
        margin: 20px 0;
        padding: 12px 16px;
        background: #e8f7ee;
        border-left: 5px solid #2ecc71;
        color: #1e7e34;
        font-weight: 600;
    ">
        ✔ Dochádzka bola úspešne uložená.
    </div>
<?php endif;


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

