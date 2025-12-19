<?php
/**
 * SPA Core – CPT Program (Tréningový program)
 *
 * Definuje tréningový program s pravidelným rozvrchom
 * 
 * @package SPA Core
 */

if (!defined('ABSPATH')) exit;

// Registrácia CPT
add_action('init', function () {

    register_post_type('spa_program', [
        'labels' => [
            'name'               => 'Programy',
            'singular_name'      => 'Program',
            'add_new'            => 'Pridať program',
            'add_new_item'       => 'Pridať nový program',
            'edit_item'          => 'Upraviť program',
            'new_item'           => 'Nový program',
            'view_item'          => 'Zobraziť program',
            'search_items'       => 'Hľadať programy',
            'not_found'          => 'Nenašli sa žiadne programy',
        ],
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => true,
        'supports'            => ['title', 'editor', 'thumbnail', 'excerpt'],
        'taxonomies'          => ['spa_category', 'post_tag'],
        'capability_type'     => 'post',
        'menu_icon'           => 'dashicons-awards',
        'menu_position'       => 24,
        'has_archive'         => true,
        'rewrite'             => ['slug' => 'programy'],
    ]);

    // Registrácia taxonomie - kategórie (vekové skupiny)
    register_taxonomy('spa_category', 'spa_program', [
        'labels' => [
            'name'          => 'Kategórie',
            'singular_name' => 'Kategória',
            'add_new_item'  => 'Pridať kategóriu',
        ],
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'kategoria'],
    ]);

});

// Meta boxy
add_action('add_meta_boxes', function () {
    
    add_meta_box(
        'spa_program_venue',
        'Miesto konania',
        'spa_program_venue_meta_box',
        'spa_program',
        'side',
        'high'
    );

    add_meta_box(
        'spa_program_capacity',
        'Kapacita a vek',
        'spa_program_capacity_meta_box',
        'spa_program',
        'side',
        'default'
    );

    add_meta_box(
        'spa_program_schedule',
        'Rozvrh tréningov',
        'spa_program_schedule_meta_box',
        'spa_program',
        'normal',
        'high'
    );

});

// Meta box: Miesto
function spa_program_venue_meta_box($post) {
    wp_nonce_field('spa_program_meta', 'spa_program_nonce');

    $venue_id = get_post_meta($post->ID, '_spa_venue_id', true);

    $venues = get_posts([
        'post_type' => 'spa_venue',
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    ?>
    <p>
        <label for="spa_venue_id"><strong>Vyberte miesto:</strong></label>
        <select id="spa_venue_id" name="spa_venue_id" style="width:100%;">
            <option value="">-- Vyberte miesto --</option>
            <?php foreach ($venues as $venue): ?>
                <option value="<?php echo $venue->ID; ?>" <?php selected($venue_id, $venue->ID); ?>>
                    <?php echo esc_html($venue->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
}

// Meta box: Kapacita a vek
function spa_program_capacity_meta_box($post) {
    $capacity = get_post_meta($post->ID, '_spa_capacity', true);
    $age_min = get_post_meta($post->ID, '_spa_age_min', true);
    $age_max = get_post_meta($post->ID, '_spa_age_max', true);

    ?>
    <p>
        <label for="spa_capacity"><strong>Kapacita (celkovo):</strong></label><br>
        <input type="number" id="spa_capacity" name="spa_capacity" value="<?php echo esc_attr($capacity); ?>" min="1" max="100" style="width:100px;">
        <span class="description">Max. detí na program</span>
    </p>

    <p>
        <label for="spa_age_min"><strong>Minimálny vek:</strong></label><br>
        <input type="text" id="spa_age_min" name="spa_age_min" value="<?php echo esc_attr($age_min); ?>" placeholder="napr. 1.8" style="width:80px;">
        <span class="description">roky</span>
    </p>

    <p>
        <label for="spa_age_max"><strong>Maximálny vek:</strong></label><br>
        <input type="text" id="spa_age_max" name="spa_age_max" value="<?php echo esc_attr($age_max); ?>" placeholder="napr. 3" style="width:80px;">
        <span class="description">roky</span>
    </p>
    <?php
}

// Meta box: Rozvrh
function spa_program_schedule_meta_box($post) {
    $schedule_times = get_post_meta($post->ID, '_spa_schedule_times', true);
    
    // Default prázdne pole
    if (!$schedule_times || !is_array($schedule_times)) {
        $schedule_times = [['day' => '', 'from' => '', 'to' => '']];
    }

    $days = [
        'monday' => 'Pondelok',
        'tuesday' => 'Utorok',
        'wednesday' => 'Streda',
        'thursday' => 'Štvrtok',
        'friday' => 'Piatok',
        'saturday' => 'Sobota',
        'sunday' => 'Nedeľa',
    ];

    ?>
    <div id="spa-schedule-wrapper">
        <p><strong>Definujte pravidelný týždenný rozvrh:</strong></p>
        
        <div id="spa-schedule-rows">
            <?php foreach ($schedule_times as $index => $slot): ?>
                <div class="spa-schedule-row" style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
                    <label>Deň:</label>
                    <select name="spa_schedule_times[<?php echo $index; ?>][day]" style="width: 150px;">
                        <option value="">-- Vyberte deň --</option>
                        <?php foreach ($days as $day_key => $day_label): ?>
                            <option value="<?php echo $day_key; ?>" <?php selected($slot['day'], $day_key); ?>>
                                <?php echo $day_label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Od:</label>
                    <input type="time" name="spa_schedule_times[<?php echo $index; ?>][from]" value="<?php echo esc_attr($slot['from'] ?? ''); ?>" style="width: 100px;">

                    <label>Do:</label>
                    <input type="time" name="spa_schedule_times[<?php echo $index; ?>][to]" value="<?php echo esc_attr($slot['to'] ?? ''); ?>" style="width: 100px;">

                    <button type="button" class="button spa-remove-schedule-row">Odstrániť</button>
                </div>
            <?php endforeach; ?>
        </div>

        <p>
            <button type="button" id="spa-add-schedule-row" class="button">+ Pridať ďalší tréning</button>
        </p>
    </div>

    <script>
    jQuery(document).ready(function($) {
        let rowIndex = <?php echo count($schedule_times); ?>;

        $('#spa-add-schedule-row').on('click', function() {
            const newRow = `
                <div class="spa-schedule-row" style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
                    <label>Deň:</label>
                    <select name="spa_schedule_times[${rowIndex}][day]" style="width: 150px;">
                        <option value="">-- Vyberte deň --</option>
                        <?php foreach ($days as $day_key => $day_label): ?>
                            <option value="<?php echo $day_key; ?>"><?php echo $day_label; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>Od:</label>
                    <input type="time" name="spa_schedule_times[${rowIndex}][from]" style="width: 100px;">

                    <label>Do:</label>
                    <input type="time" name="spa_schedule_times[${rowIndex}][to]" style="width: 100px;">

                    <button type="button" class="button spa-remove-schedule-row">Odstrániť</button>
                </div>
            `;
            $('#spa-schedule-rows').append(newRow);
            rowIndex++;
        });

        $(document).on('click', '.spa-remove-schedule-row', function() {
            $(this).closest('.spa-schedule-row').remove();
        });
    });
    </script>
    <?php
}

// Uloženie meta údajov
add_action('save_post_spa_program', function ($post_id) {
    if (!isset($_POST['spa_program_nonce']) || !wp_verify_nonce($_POST['spa_program_nonce'], 'spa_program_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Uloženie venue
    if (isset($_POST['spa_venue_id'])) {
        update_post_meta($post_id, '_spa_venue_id', absint($_POST['spa_venue_id']));
    }

    // Uloženie kapacity
    if (isset($_POST['spa_capacity'])) {
        update_post_meta($post_id, '_spa_capacity', absint($_POST['spa_capacity']));
    }

    // Uloženie veku
    if (isset($_POST['spa_age_min'])) {
        update_post_meta($post_id, '_spa_age_min', sanitize_text_field($_POST['spa_age_min']));
    }

    if (isset($_POST['spa_age_max'])) {
        update_post_meta($post_id, '_spa_age_max', sanitize_text_field($_POST['spa_age_max']));
    }

    // Uloženie rozvrhu (JSON)
    if (isset($_POST['spa_schedule_times']) && is_array($_POST['spa_schedule_times'])) {
        $schedule_times = array_filter($_POST['spa_schedule_times'], function($slot) {
            return !empty($slot['day']) && !empty($slot['from']) && !empty($slot['to']);
        });
        update_post_meta($post_id, '_spa_schedule_times', $schedule_times);
    }
});

// Vlastné stĺpce v admin liste
add_filter('manage_spa_program_posts_columns', function ($columns) {
    $new_columns = [
        'cb' => $columns['cb'],
        'title' => 'Názov',
        'taxonomy-spa_category' => 'Kategória',
        'venue' => 'Miesto',
        'capacity' => 'Kapacita',
        'schedule_days' => 'Tréningové dni',
        'date' => 'Vytvorené',
    ];
    return $new_columns;
});

add_action('manage_spa_program_posts_custom_column', function ($column, $post_id) {
    switch ($column) {
        case 'venue':
            $venue_id = get_post_meta($post_id, '_spa_venue_id', true);
            if ($venue_id) {
                echo get_the_title($venue_id);
            } else {
                echo '—';
            }
            break;

        case 'capacity':
            $capacity = get_post_meta($post_id, '_spa_capacity', true);
            echo $capacity ?: '∞';
            break;

        case 'schedule_days':
            $schedule_times = get_post_meta($post_id, '_spa_schedule_times', true);
            if ($schedule_times && is_array($schedule_times)) {
                $days_map = [
                    'monday' => 'PO',
                    'tuesday' => 'UT',
                    'wednesday' => 'ST',
                    'thursday' => 'ŠT',
                    'friday' => 'PI',
                    'saturday' => 'SO',
                    'sunday' => 'NE',
                ];
                $output = [];
                foreach ($schedule_times as $slot) {
                    $day_short = $days_map[$slot['day']] ?? $slot['day'];
                    $output[] = $day_short . ' ' . $slot['from'];
                }
                echo implode(', ', $output);
            } else {
                echo '—';
            }
            break;
    }
}, 10, 2);