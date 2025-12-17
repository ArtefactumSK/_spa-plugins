<?php
/**
 * Plugin Name: SPA Core
 * Description: Core systém pre Samuel Piasecky ACADEMY (DB, logika, integrácie).
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// DB install
require_once __DIR__ . '/includes/db/install.php';

register_activation_hook(__FILE__, 'spa_core_install_db');
