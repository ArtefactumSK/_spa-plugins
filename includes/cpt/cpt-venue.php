<?php
/**
 * SPA Core – CPT Venue (Miesto)
 *
 * Tréningové haly a ich lokácie
 * 
 * @package SPA Core
 */

if (!defined('ABSPATH')) exit;

// Registrácia CPT
add_action('init', function () {

    register_post_type('spa_venue', [
        'labels' => [
            'name'               => 'Miesta',
            'singular_name'      => 'Miesto',
            'add_new'            => 'Pridať miesto',
            'add_new_item'       => 'Pridať nové miesto',
            'edit_item'          => 'Upraviť miesto',
            'new_item'           => 'Nové miesto',
            'view_item'          => 'Zobraziť miesto',
            'search_items'       => 'Hľadať miesta',
            'not_found'          => 'Nenašli sa žiadne miesta',
        ],
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => true,
        'supports'            => ['title', 'editor', 'thumbnail'],
        'capability_type'     => 'post',
        'menu_icon'           => 'dashicons-location',
        'menu_position'       => 26,
        'has_archive'         => true,
        'rewrite'             => ['slug' => 'miesta'],
    ]);

});

// Meta boxy
add_action('add_meta_boxes', function () {
    
    add_meta_box(
        'spa_venue_address',
        'Adresa',
        'spa_venue_address_meta_box',
        'spa_venue',
        'side',
        'high'
    );

    add_meta_box(
        'spa_venue_gps',
        'GPS súradnice',
        'spa_venue_gps_meta_box',
        'spa_venue',
        'side',
        'default'
    );

});

// Meta box: Adresa
function spa_venue_address_meta_box($post) {
    wp_nonce_field('spa_venue_meta', 'spa_venue_nonce');

    $address = get_post_meta($post->ID, '_spa_address', true);
    $city = get_post_meta($post->ID, '_spa_city', true);
    $zip = get_post_meta($post->ID, '_spa_zip', true);

    ?>
    <p>
        <label for="spa_address"><strong>Ulica a číslo:</strong></label><br>
        <input type="text" id="spa_address" name="spa_address" value="<?php echo esc_attr($address); ?>" style="width:100%;" placeholder="napr. Hlavná 123">
    </p>

    <p>
        <label for="spa_city"><strong>Mesto:</strong></label><br>
        <input type="text" id="spa_city" name="spa_city" value="<?php echo esc_attr($city); ?>" style="width:100%;" placeholder="napr. Malacky">
    </p>

    <p>
        <label for="spa_zip"><strong>PSČ:</strong></label><br>
        <input type="text" id="spa_zip" name="spa_zip" value="<?php echo esc_attr($zip); ?>" style="width:100px;" placeholder="90101">
    </p>
    <?php
}

// Meta box: GPS
function spa_venue_gps_meta_box($post) {
    $lat = get_post_meta($post->ID, '_spa_lat', true);
    $lon = get_post_meta($post->ID, '_spa_lon', true);

    ?>
    <p>
        <label for="spa_lat"><strong>Latitude (šírka):</strong></label><br>
        <input type="text" id="spa_lat" name="spa_lat" value="<?php echo esc_attr($lat); ?>" style="width:100%;" placeholder="48.123456">
    </p>

    <p>
        <label for="spa_lon"><strong>Longitude (dĺžka):</strong></label><br>
        <input type="text" id="spa_lon" name="spa_lon" value="<?php echo esc_attr($lon); ?>" style="width:100%;" placeholder="17.123456">
    </p>

    <p class="description">
        GPS súradnice pre mapy. Nájdite na <a href="https://www.google.com/maps" target="_blank">Google Maps</a>.
    </p>
    <?php
}

// Uloženie meta údajov
add_action('save_post_spa_venue', function ($post_id) {
    if (!isset($_POST['spa_venue_nonce']) || !wp_verify_nonce($_POST['spa_venue_nonce'], 'spa_venue_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Uloženie adresy
    if (isset($_POST['spa_address'])) {
        update_post_meta($post_id, '_spa_address', sanitize_text_field($_POST['spa_address']));
    }

    if (isset($_POST['spa_city'])) {
        update_post_meta($post_id, '_spa_city', sanitize_text_field($_POST['spa_city']));
    }

    if (isset($_POST['spa_zip'])) {
        update_post_meta($post_id, '_spa_zip', sanitize_text_field($_POST['spa_zip']));
    }

    // Uloženie GPS
    if (isset($_POST['spa_lat'])) {
        update_post_meta($post_id, '_spa_lat', sanitize_text_field($_POST['spa_lat']));
    }

    if (isset($_POST['spa_lon'])) {
        update_post_meta($post_id, '_spa_lon', sanitize_text_field($_POST['spa_lon']));
    }
});

// Vlastné stĺpce v admin liste
add_filter('manage_spa_venue_posts_columns', function ($columns) {
    $new_columns = [
        'cb' => $columns['cb'],
        'title' => 'Názov',
        'address' => 'Adresa',
        'city' => 'Mesto',
        'gps' => 'GPS',
        'date' => 'Vytvorené',
    ];
    return $new_columns;
});

add_action('manage_spa_venue_posts_custom_column', function ($column, $post_id) {
    switch ($column) {
        case 'address':
            $address = get_post_meta($post_id, '_spa_address', true);
            echo $address ?: '—';
            break;

        case 'city':
            $city = get_post_meta($post_id, '_spa_city', true);
            $zip = get_post_meta($post_id, '_spa_zip', true);
            echo $zip ? $zip . ' ' . $city : ($city ?: '—');
            break;

        case 'gps':
            $lat = get_post_meta($post_id, '_spa_lat', true);
            $lon = get_post_meta($post_id, '_spa_lon', true);
            if ($lat && $lon) {
                echo '<a href="https://www.google.com/maps?q=' . $lat . ',' . $lon . '" target="_blank">Mapa</a>';
            } else {
                echo '—';
            }
            break;
    }
}, 10, 2);