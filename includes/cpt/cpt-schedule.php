<?php
/**
 * SPA Core – CPT Schedule (Rozvrh)
 *
 * Jeden záznam = jeden tréningový termín programu
 * 
 * @package SPA Core
 */

if (!defined('ABSPATH')) {
    exit;
}

// Registrácia CPT
add_action('init', function () {

    register_post_type('spa_schedule', [
        'labels' => [
            'name'               => 'Rozvrhy',
            'singular_name'      => 'Rozvrh',
            'add_new'            => 'Pridať rozvrh',
            'add_new_item'       => 'Pridať nový rozvrh',
            'edit_item'          => 'Upraviť rozvrh',
            'new_item'           => 'Nový rozvrh',
            'view_item'          => 'Zobraziť rozvrh',
            'search_items'       => 'Hľadať rozvrhy',
            'not_found'          => 'Nenašli sa žiadne rozvrhy',
            'not_found_in_trash' => 'V koši nie sú žiadne rozvrhy',
        ],
        'public'              => true,
        'show_ui'             => true,  // ✅ ZAPNUTÉ
        'show_in_menu'        => true,  // ✅ ZOBRAZ V MENU
        'show_in_rest'        => true,
        'supports'            => ['title'],
        'capability_type'     => 'post',
        'menu_icon'           => 'dashicons-calendar-alt',
        'menu_position'       => 25,
    ]);

});

// Meta boxy pre Schedule
add_action('add_meta_boxes', function () {
    add_meta_box(
        'spa_schedule_details',
        'Detaily rozvrhu',
        'spa_schedule_meta_box_callback',
        'spa_schedule',
        'normal',
        'high'
    );
});

function spa_schedule_meta_box_callback($post) {
    // Nonce pre bezpečnosť
    wp_nonce_field('spa_schedule_meta_box', 'spa_schedule_meta_box_nonce');

    // Načítaj aktuálne hodnoty
    $capacity = get_post_meta($post->ID, '_spa_capacity', true);
    $date = get_post_meta($post->ID, '_spa_date', true);
    $time = get_post_meta($post->ID, '_spa_time', true);
    $venue_id = get_post_meta($post->ID, '_spa_venue_id', true);
    $program_id = get_post_meta($post->ID, '_spa_program_id', true);

    // Načítaj dostupné venues
    $venues = get_posts([
        'post_type' => 'spa_venue',
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    // Načítaj dostupné programy z DB
    global $wpdb;
    $programs = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}spa_programs ORDER BY name");

    ?>
    <table class="form-table">
        <tr>
            <th><label for="spa_capacity">Kapacita</label></th>
            <td>
                <input type="number" id="spa_capacity" name="spa_capacity" value="<?php echo esc_attr($capacity); ?>" min="1" max="100" style="width: 100px;">
                <p class="description">Maximálny počet detí na tomto tréningu</p>
            </td>
        </tr>
        <tr>
            <th><label for="spa_date">Dátum</label></th>
            <td>
                <input type="date" id="spa_date" name="spa_date" value="<?php echo esc_attr($date); ?>">
                <p class="description">Dátum tréningu (ak je fixný)</p>
            </td>
        </tr>
        <tr>
            <th><label for="spa_time">Čas</label></th>
            <td>
                <input type="time" id="spa_time" name="spa_time" value="<?php echo esc_attr($time); ?>">
                <p class="description">Čas začiatku tréningu (napr. 16:00)</p>
            </td>
        </tr>
        <tr>
            <th><label for="spa_venue_id">Miesto</label></th>
            <td>
                <select id="spa_venue_id" name="spa_venue_id">
                    <option value="">-- Vyberte miesto --</option>
                    <?php foreach ($venues as $venue): ?>
                        <option value="<?php echo $venue->ID; ?>" <?php selected($venue_id, $venue->ID); ?>>
                            <?php echo esc_html($venue->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="spa_program_id">Program (DB)</label></th>
            <td>
                <select id="spa_program_id" name="spa_program_id">
                    <option value="">-- Vyberte program --</option>
                    <?php if ($programs): ?>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?php echo $program->id; ?>" <?php selected($program_id, $program->id); ?>>
                                <?php echo esc_html($program->name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <p class="description">Program z DB tabuľky spa_programs</p>
            </td>
        </tr>
    </table>

    <?php
    // Zobraz štatistiky
    $registrations_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}spa_registrations WHERE schedule_id = %d",
        $post->ID
    ));
    ?>

    <hr>
    <h3>Štatistiky</h3>
    <p><strong>Počet registrácií:</strong> <?php echo $registrations_count; ?> / <?php echo $capacity ?: '∞'; ?></p>
    <?php
}

// Uloženie meta údajov
add_action('save_post_spa_schedule', function ($post_id) {
    // Overenie nonce
    if (!isset($_POST['spa_schedule_meta_box_nonce']) || !wp_verify_nonce($_POST['spa_schedule_meta_box_nonce'], 'spa_schedule_meta_box')) {
        return;
    }

    // Overenie autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Overenie oprávnení
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Uloženie meta údajov
    if (isset($_POST['spa_capacity'])) {
        update_post_meta($post_id, '_spa_capacity', absint($_POST['spa_capacity']));
    }

    if (isset($_POST['spa_date'])) {
        update_post_meta($post_id, '_spa_date', sanitize_text_field($_POST['spa_date']));
    }

    if (isset($_POST['spa_time'])) {
        update_post_meta($post_id, '_spa_time', sanitize_text_field($_POST['spa_time']));
    }

    if (isset($_POST['spa_venue_id'])) {
        update_post_meta($post_id, '_spa_venue_id', absint($_POST['spa_venue_id']));
    }

    if (isset($_POST['spa_program_id'])) {
        update_post_meta($post_id, '_spa_program_id', absint($_POST['spa_program_id']));
    }
});

// Vlastné stĺpce v admin liste
add_filter('manage_spa_schedule_posts_columns', function ($columns) {
    $new_columns = [
        'cb' => $columns['cb'],
        'title' => 'Názov',
        'capacity' => 'Kapacita',
        'registrations' => 'Registrácie',
        'date_time' => 'Dátum / Čas',
        'venue' => 'Miesto',
        'date' => 'Vytvorené',
    ];
    return $new_columns;
});

add_action('manage_spa_schedule_posts_custom_column', function ($column, $post_id) {
    global $wpdb;

    switch ($column) {
        case 'capacity':
            $capacity = get_post_meta($post_id, '_spa_capacity', true);
            echo $capacity ?: '∞';
            break;

        case 'registrations':
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}spa_registrations WHERE schedule_id = %d",
                $post_id
            ));
            $capacity = get_post_meta($post_id, '_spa_capacity', true);
            echo $count . ' / ' . ($capacity ?: '∞');
            break;

        case 'date_time':
            $date = get_post_meta($post_id, '_spa_date', true);
            $time = get_post_meta($post_id, '_spa_time', true);
            if ($date) {
                echo date('d.m.Y', strtotime($date));
            }
            if ($time) {
                echo ' o ' . $time;
            }
            if (!$date && !$time) {
                echo '—';
            }
            break;

        case 'venue':
            $venue_id = get_post_meta($post_id, '_spa_venue_id', true);
            if ($venue_id) {
                echo get_the_title($venue_id);
            } else {
                echo '—';
            }
            break;
    }
}, 10, 2);