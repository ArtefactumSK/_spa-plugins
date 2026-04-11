<?php
/**
 * Plugin Name: SPA Register GF
 * Description: SPA registrácia cez Gravity Forms na základe $_SESSION['spa_registration'] (spa-selection → /register).
 * Version: 1.0.0
 * Author: SPA System
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'spa_debug_log' ) ) {
    /**
     * Centralized SPA debug logger fallback for plugin context.
     *
     * @param mixed $message
     */
    function spa_debug_log( $message ): void {
        $text = is_scalar( $message ) ? (string) $message : wp_json_encode( $message );
        $is_spa_log = is_string( $text ) && stripos( $text, 'spa' ) !== false;
        if ( $is_spa_log && !( defined( 'SPA_DEBUG' ) && SPA_DEBUG === true ) ) {
            return;
        }
        error_log( $text );
    }
}

// Guard proti dvojitému načítaniu
if ( defined( 'SPA_REG_GF_VERSION' ) ) {
    return;
}

define( 'SPA_REG_GF_FILE',         __FILE__ );
define( 'SPA_REG_GF_DIR',          plugin_dir_path( __FILE__ ) );
define( 'SPA_REG_GF_URL',          plugin_dir_url( __FILE__ ) );
define( 'SPA_REG_GF_VERSION',      '1.0.0' );
define( 'SPA_REG_GF_SESSION_KEY',  'spa_registration' );
define( 'SPA_REG_GF_SESSION_TTL',  1800 );
define( 'SPA_REG_GF_CSS_CLASS',    'spa-register-gf' );
define( 'SPA_REG_GF_SELECTOR_URL', '/spa-selector' );
define( 'SPA_REG_GF_DB_VERSION',   '1.3.0' );

require_once SPA_REG_GF_DIR . 'src/Bootstrap/Plugin.php';

/**
 * Installer (Faza A+B): vytvorenie DB tabulky pre DB-first registracie.
 *
 * @return bool true ak je tabulka dostupna po pokuse o install
 */
function spa_reg_gf_install_or_upgrade(): bool {
    global $wpdb;

    if ( ! isset( $wpdb ) || ! ( $wpdb instanceof wpdb ) ) {
        return false;
    }

    if ( ! class_exists( '\SpaRegisterGf\Infrastructure\Logger', false ) ) {
        require_once SPA_REG_GF_DIR . 'src/Infrastructure/Logger.php';
    }

    $table_name = $wpdb->prefix . 'spa_registrations';
    $charset_collate = $wpdb->get_charset_collate();
    $expected_columns = [
        'id',
        'client_user_id',
        'parent_user_id',
        'program_id',
        'status',
        'amount',
        'frequency_key',
        'spa_vs',
        'payment_method',
        'invoice_to_company',
        'invoice_address_different',
        'company_name',
        'company_ico',
        'company_dic',
        'company_icdph',
        'company_address_street',
        'company_address_city',
        'company_address_postcode',
        'company_address_country',
        'created_at',
        'updated_at',
    ];

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $exists_before = (string) $wpdb->get_var(
        $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
    ) === $table_name;

    if ( $exists_before ) {
        $columns_raw = $wpdb->get_results( "DESCRIBE {$table_name}", ARRAY_A );
        $actual_columns = [];
        foreach ( (array) $columns_raw as $row ) {
            if ( is_array( $row ) && isset( $row['Field'] ) ) {
                $actual_columns[] = (string) $row['Field'];
            }
        }

        $extra_columns = array_values( array_diff( $actual_columns, $expected_columns ) );
        $missing_columns = array_values( array_diff( $expected_columns, $actual_columns ) );

        if ( ! empty( $extra_columns ) ) {
            \SpaRegisterGf\Infrastructure\Logger::info( 'installer_spa_registrations_drop_recreate', [
                'table' => $table_name,
                'extra_columns' => $extra_columns,
                'missing_columns' => $missing_columns,
            ] );
            $drop_sql = "DROP TABLE IF EXISTS {$table_name}";
            $drop_ok = $wpdb->query( $drop_sql );
            if ( $drop_ok === false ) {
                \SpaRegisterGf\Infrastructure\Logger::error( 'installer_spa_registrations_drop_failed', [
                    'table' => $table_name,
                    'last_error' => (string) $wpdb->last_error,
                ] );
                return false;
            }
        }
        if ( ! empty( $missing_columns ) ) {
            \SpaRegisterGf\Infrastructure\Logger::info( 'installer_spa_registrations_missing_columns', [
                'table' => $table_name,
                'missing_columns' => $missing_columns,
                'strategy' => 'dbdelta_add_columns',
            ] );
        }
    }

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        client_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        parent_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        program_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(32) NOT NULL DEFAULT 'pending',
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        frequency_key VARCHAR(50) NOT NULL DEFAULT '',
        spa_vs VARCHAR(32) NOT NULL DEFAULT '',
        payment_method VARCHAR(50) NOT NULL DEFAULT '',
        invoice_to_company TINYINT(1) NOT NULL DEFAULT 0,
        invoice_address_different TINYINT(1) NOT NULL DEFAULT 0,
        company_name VARCHAR(191) NOT NULL DEFAULT '',
        company_ico VARCHAR(64) NOT NULL DEFAULT '',
        company_dic VARCHAR(64) NOT NULL DEFAULT '',
        company_icdph VARCHAR(64) NOT NULL DEFAULT '',
        company_address_street VARCHAR(191) NOT NULL DEFAULT '',
        company_address_city VARCHAR(191) NOT NULL DEFAULT '',
        company_address_postcode VARCHAR(64) NOT NULL DEFAULT '',
        company_address_country VARCHAR(191) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY client_user_id (client_user_id),
        KEY parent_user_id (parent_user_id),
        KEY program_id (program_id),
        KEY status (status),
        UNIQUE KEY unique_spa_vs (spa_vs)
    ) {$charset_collate};";

    $result = dbDelta( $sql );

    $exists = (string) $wpdb->get_var(
        $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
    ) === $table_name;

    if ( $exists ) {
        \SpaRegisterGf\Infrastructure\Logger::info( 'installer_spa_registrations_ready', [
            'table' => $table_name,
            'db_version' => SPA_REG_GF_DB_VERSION,
            'dbdelta_result' => is_array( $result ) ? $result : [],
        ] );
        return true;
    }

    \SpaRegisterGf\Infrastructure\Logger::error( 'installer_spa_registrations_failed', [
        'table' => $table_name,
        'db_version' => SPA_REG_GF_DB_VERSION,
        'last_error' => (string) $wpdb->last_error,
        'dbdelta_result' => is_array( $result ) ? $result : [],
    ] );
    return false;
}

/**
 * Adds UNIQUE(spa_vs) on wp_spa_registrations when safe.
 * If duplicate spa_vs values exist, logs them and does not ALTER (manual cleanup required).
 */
function spa_reg_gf_maybe_add_unique_spa_vs(): void {
    global $wpdb;

    if ( ! isset( $wpdb ) || ! ( $wpdb instanceof wpdb ) ) {
        return;
    }

    $table = $wpdb->prefix . 'spa_registrations';
    if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
        return;
    }

    if ( get_option( 'spa_reg_gf_vs_unique_applied' ) === 'yes' ) {
        return;
    }

    $has_unique = $wpdb->get_results(
        $wpdb->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", 'unique_spa_vs' ),
        ARRAY_A
    );
    if ( ! empty( $has_unique ) ) {
        update_option( 'spa_reg_gf_vs_unique_applied', 'yes', true );
        return;
    }

    $dup_groups = $wpdb->get_results(
        "SELECT spa_vs, COUNT(*) AS cnt FROM `{$table}` GROUP BY spa_vs HAVING cnt > 1",
        ARRAY_A
    );
    if ( ! empty( $dup_groups ) ) {
        if ( ! get_transient( 'spa_reg_gf_vs_dup_logged' ) ) {
            foreach ( $dup_groups as $row ) {
                $vs_val = isset( $row['spa_vs'] ) ? (string) $row['spa_vs'] : '';
                $cnt     = isset( $row['cnt'] ) ? (int) $row['cnt'] : 0;
                $line    = '[SPA VS UNIQUE MIGRATION] duplicate spa_vs — vs=' . $vs_val . ' count=' . $cnt;
                error_log( $line );
                if ( class_exists( '\SpaRegisterGf\Infrastructure\Logger', false ) ) {
                    \SpaRegisterGf\Infrastructure\Logger::error(
                        'spa_registrations_duplicate_spa_vs',
                        [
                            'spa_vs' => $vs_val,
                            'count'  => $cnt,
                        ]
                    );
                }
            }
            set_transient( 'spa_reg_gf_vs_dup_logged', 1, DAY_IN_SECONDS );
        }
        return;
    }

    $key_spa_vs = $wpdb->get_results(
        "SHOW INDEX FROM `{$table}` WHERE Key_name = 'spa_vs'",
        ARRAY_A
    );
    if ( ! empty( $key_spa_vs ) ) {
        $wpdb->query( "ALTER TABLE `{$table}` DROP INDEX `spa_vs`" );
    }

    $altered = $wpdb->query( "ALTER TABLE `{$table}` ADD UNIQUE KEY `unique_spa_vs` (`spa_vs`)" );
    if ( $altered === false ) {
        error_log( '[SPA VS UNIQUE MIGRATION] ALTER failed: ' . (string) $wpdb->last_error );
        if ( class_exists( '\SpaRegisterGf\Infrastructure\Logger', false ) ) {
            \SpaRegisterGf\Infrastructure\Logger::error(
                'spa_registrations_unique_spa_vs_failed',
                [
                    'last_error' => (string) $wpdb->last_error,
                ]
            );
        }
        return;
    }

    update_option( 'spa_reg_gf_vs_unique_applied', 'yes', true );
    error_log( '[SPA VS UNIQUE MIGRATION] UNIQUE KEY unique_spa_vs applied on ' . $table );
}

register_activation_hook( __FILE__, function () {
    delete_transient( 'spa_reg_gf_form_id' );
    $ok = spa_reg_gf_install_or_upgrade();
    if ( $ok ) {
        spa_reg_gf_maybe_add_unique_spa_vs();
        update_option( 'spa_reg_gf_version', SPA_REG_GF_DB_VERSION );
    }
} );

register_deactivation_hook( __FILE__, function () {
    delete_transient( 'spa_reg_gf_form_id' );
} );

add_action( 'plugins_loaded', function () {
    $installed_version = (string) get_option( 'spa_reg_gf_version', '' );
    if ( $installed_version === '' || version_compare( $installed_version, SPA_REG_GF_DB_VERSION, '<' ) ) {
        $ok = spa_reg_gf_install_or_upgrade();
        if ( $ok ) {
            spa_reg_gf_maybe_add_unique_spa_vs();
            update_option( 'spa_reg_gf_version', SPA_REG_GF_DB_VERSION );
        }
    } else {
        spa_reg_gf_maybe_add_unique_spa_vs();
    }
    SpaRegisterGf\Bootstrap\Plugin::boot();
}, 20 );

add_action('after_setup_theme', function () {

    if (
        interface_exists('\SpaSystem\Settings\RegistrationModuleInterface') &&
        class_exists('\SpaSystem\Settings\RegistrationModuleRegistry')
    ) {

        require_once __DIR__ . '/includes/GFRegistrationModule.php';

        \SpaSystem\Settings\RegistrationModuleRegistry::register(
            new \SpaRegisterGF\GFRegistrationModule()
        );
    }

});