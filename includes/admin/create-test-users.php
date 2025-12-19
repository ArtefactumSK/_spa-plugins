<?php
/**
 * Create Test Users for SPA System
 * 
 * Vytvorí testovacie účty pre všetky roly
 * 
 * POUŽITIE:
 * 1. Choď do WP Admin → Nástroje → SPA Test Users
 * 2. Klikni "Vytvoriť testovacie účty"
 * 
 * @package SPA Core
 */

if (!defined('ABSPATH')) exit;

// Pridaj menu v admin
add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php',
        'SPA Test Users',
        'SPA Test Users',
        'manage_options',
        'spa-test-users',
        'spa_test_users_page'
    );
});

function spa_test_users_page() {
    ?>
    <div class="wrap">
        <h1>🧪 SPA Test Users</h1>

        <?php
        if (isset($_POST['create_test_users']) && check_admin_referer('spa_test_users')) {
            spa_create_test_users();
        }
        ?>

        <form method="post">
            <?php wp_nonce_field('spa_test_users'); ?>
            <p>Vytvorí testovacie účty pre všetky SPA roly:</p>
            <ul>
                <li><strong>SPA Owner:</strong> owner@test.spa (heslo: TestOwner123!)</li>
                <li><strong>SPA Manager:</strong> manager@test.spa (heslo: TestManager123!)</li>
                <li><strong>SPA Trainer:</strong> trainer@test.spa (heslo: TestTrainer123!)</li>
                <li><strong>SPA Parent:</strong> parent@test.spa (heslo: TestParent123!)</li>
                <li><strong>SPA Child:</strong> child@test.spa (heslo: TestChild123!)</li>
            </ul>
            <p class="submit">
                <input type="submit" name="create_test_users" class="button button-primary" value="Vytvoriť testovacie účty">
            </p>
        </form>

        <hr>

        <h2>Existujúce SPA účty:</h2>
        <?php spa_list_spa_users(); ?>
    </div>
    <?php
}

function spa_create_test_users() {
    $users = [
        [
            'username' => 'spa_owner_test',
            'email' => 'owner@test.spa',
            'password' => 'TestOwner123!',
            'role' => 'spa_owner',
            'display_name' => 'Test Owner',
        ],
        [
            'username' => 'spa_manager_test',
            'email' => 'manager@test.spa',
            'password' => 'TestManager123!',
            'role' => 'spa_manager',
            'display_name' => 'Test Manager',
        ],
        [
            'username' => 'spa_trainer_test',
            'email' => 'trainer@test.spa',
            'password' => 'TestTrainer123!',
            'role' => 'spa_trainer',
            'display_name' => 'Test Trainer',
        ],
        [
            'username' => 'spa_parent_test',
            'email' => 'parent@test.spa',
            'password' => 'TestParent123!',
            'role' => 'spa_parent',
            'display_name' => 'Test Rodič',
        ],
        [
            'username' => 'spa_child_test',
            'email' => 'child@test.spa',
            'password' => 'TestChild123!',
            'role' => 'spa_child',
            'display_name' => 'Test Dieťa',
        ],
    ];

    $results = [];

    foreach ($users as $user_data) {
        // Over, či user už existuje
        $existing = get_user_by('email', $user_data['email']);
        
        if ($existing) {
            $results[] = '⚠️ User <strong>' . $user_data['email'] . '</strong> už existuje (ID: ' . $existing->ID . ')';
            continue;
        }

        // Vytvor usera
        $user_id = wp_create_user(
            $user_data['username'],
            $user_data['password'],
            $user_data['email']
        );

        if (is_wp_error($user_id)) {
            $results[] = '❌ Chyba pri vytváraní <strong>' . $user_data['email'] . '</strong>: ' . $user_id->get_error_message();
            continue;
        }

        // Nastav display name
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $user_data['display_name'],
        ]);

        // Priraď rolu
        $user = new WP_User($user_id);
        $user->set_role($user_data['role']);

        // Ak je parent, vytvor záznam v spa_parents
        if ($user_data['role'] === 'spa_parent') {
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'spa_parents',
                [
                    'user_id' => $user_id,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s']
            );
        }

        $results[] = '✅ Vytvorený: <strong>' . $user_data['email'] . '</strong> (heslo: ' . $user_data['password'] . ')';
    }

    echo '<div class="notice notice-success"><p><strong>Výsledky:</strong></p><ul>';
    foreach ($results as $result) {
        echo '<li>' . $result . '</li>';
    }
    echo '</ul></div>';
}

function spa_list_spa_users() {
    $roles = ['spa_owner', 'spa_manager', 'spa_trainer', 'spa_parent', 'spa_child'];
    
    $users = get_users([
        'role__in' => $roles,
        'orderby' => 'registered',
        'order' => 'DESC',
    ]);

    if (!$users) {
        echo '<p>Žiadne SPA účty.</p>';
        return;
    }

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Meno</th><th>Email</th><th>Rola</th><th>Registrovaný</th></tr></thead>';
    echo '<tbody>';

    foreach ($users as $user) {
        echo '<tr>';
        echo '<td>' . $user->ID . '</td>';
        echo '<td>' . esc_html($user->display_name) . '</td>';
        echo '<td>' . esc_html($user->user_email) . '</td>';
        echo '<td>' . implode(', ', $user->roles) . '</td>';
        echo '<td>' . $user->user_registered . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}