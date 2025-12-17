<?php
/**
 * SPA Core – Roles & Capabilities
 *
 * Definuje biznis roly pre SPA systém.
 */

if (!defined('ABSPATH')) {
    exit;
}

function spa_core_register_roles() {

    // OWNER
    add_role('spa_owner', 'SPA Owner', [
        'read' => true,
        'spa_view_all_registrations' => true,
        'spa_manage_settings'        => true,
    ]);

    // MANAGER
    add_role('spa_manager', 'SPA Manager', [
        'read' => true,
        'spa_view_all_registrations' => true,
    ]);

    // TRAINER
    add_role('spa_trainer', 'SPA Trainer', [
        'read' => true,
        'spa_view_assigned_registrations' => true,
    ]);

    // PARENT
    add_role('spa_parent', 'SPA Parent', [
        'read' => true,
        'spa_view_own_registrations' => true,
    ]);
}
