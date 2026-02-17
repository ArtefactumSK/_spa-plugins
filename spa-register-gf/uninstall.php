<?php
/**
 * SPA System REGISTER Uninstall
 * Spustí sa pri odstránení pluginu cez WordPress admin
 */

// Zabránenie priamemu prístupu
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// V súčasnej fáze plugin nemazuje žiadne dáta
// Tento súbor je pripravený pre budúce použitie

/**
 * BUDÚCE AKCIE PRI ODINSTALÁCII (zatiaľ neaktívne):
 * 
 * - Zmazanie custom tabuliek (wp_spa_*)
 * - Zmazanie options (spa_*)
 * - Zmazanie user meta
 * - Zmazanie transients
 * - Vyčistenie scheduled events
 */

// Placeholder pre budúce použitie
do_action('spa_uninstall');