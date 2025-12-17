<?php
/**
 * SPA Core – Database Schema
 * 
 * Tento súbor obsahuje LEN definície SQL tabuliek.
 * Nespúšťa sa sám. Používa sa v install.php cez dbDelta().
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vráti SQL schému pre SPA Core tabuľky
 *
 * @return string
 */
function spa_core_get_schema_sql() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = <<<SQL

/* Projekt SPA (Samuel Piasecky ACADEMY)
WP multisite/ pracovna URL spa.artepaint.eu
DB artepaint_eu
DB_HOST 'db-05.nameserver.sk'
$table_prefix = 'wp_ap';
Author: Mgr. Roman Valent/AI assistants
*/


// DB TABULKY k subdomene spa.artepaint.eu

wp_ap5_actionscheduler_actions
wp_ap5_actionscheduler_claims
wp_ap5_actionscheduler_groups
wp_ap5_actionscheduler_logs
wp_ap5_b2s_network_insights
wp_ap5_b2s_posts
wp_ap5_b2s_posts_drafts
wp_ap5_b2s_posts_favorites
wp_ap5_b2s_posts_insights
wp_ap5_b2s_posts_network_details
wp_ap5_b2s_posts_sched_details
wp_ap5_b2s_user
wp_ap5_b2s_user_contact
wp_ap5_b2s_user_network_settings
wp_ap5_b2s_user_tool
wp_ap5_commentmeta
wp_ap5_comments
wp_ap5_e_events
wp_ap5_gf_addon_feed
wp_ap5_gf_draft_submissions
wp_ap5_gf_entry
wp_ap5_gf_entry_meta
wp_ap5_gf_entry_notes
wp_ap5_gf_form
wp_ap5_gf_form_meta
wp_ap5_gf_form_revisions
wp_ap5_gf_form_view
wp_ap5_gf_rest_api_keys
wp_ap5_links
wp_ap5_litespeed_url
wp_ap5_litespeed_url_file
wp_ap5_options
wp_ap5_postmeta
wp_ap5_posts
wp_ap5_term_relationships
wp_ap5_term_taxonomy
wp_ap5_termmeta
wp_ap5_terms
wp_ap5_trp_dictionary_sk_sk_en_us
wp_ap5_trp_gettext_en_us
wp_ap5_trp_gettext_original_meta
wp_ap5_trp_gettext_original_strings
wp_ap5_trp_gettext_sk_sk
wp_ap5_trp_original_meta
wp_ap5_trp_original_strings
 
wp_ap5_wpmailsmtp_debug_events
wp_ap5_wpmailsmtp_tasks_meta
wp_apusermeta
wp_apusers


SQL;

    return $sql;
}
