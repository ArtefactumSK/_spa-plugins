<?php
namespace SpaRegisterGf\Bootstrap;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    private static bool $booted = false;

    /**
     * Boot pluginu – bezpečne len raz.
     */
    public static function boot(): void {
        if ( session_status() === PHP_SESSION_NONE && ! headers_sent() ) {
            session_start();
        }
    
        if ( self::$booted ) {
            return;
        }

        // Autoload musí byť prvý, aby sa dali loadnúť triedy v wireHooks()
        self::registerAutoload();

        // Dependency guard
        if ( ! self::dependenciesOk() ) {
            return;
        }

        self::wireHooks();

        self::$booted = true;

        // DEBUG len v debug režime
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( 'SPA-REGISTER-GF: Plugin booted successfully' );
        }
    }

    /**
     * PSR-4-ish autoload pre namespace SpaRegisterGf\
     */
    private static function registerAutoload(): void {
        spl_autoload_register( function ( string $class ): void {
            $prefix  = 'SpaRegisterGf\\';
            $baseDir = defined('SPA_REG_GF_DIR') ? ( SPA_REG_GF_DIR . 'src/' ) : null;

            if ( ! $baseDir ) {
                return;
            }

            if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
                return;
            }

            $relative = substr( $class, strlen( $prefix ) );
            $file     = $baseDir . str_replace( '\\', '/', $relative ) . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        } );
    }

    /**
     * Gravity Forms musí byť aktívny.
     * (Na FE len ticho skončíme, v admin-e ukážeme notice.)
     */
    private static function dependenciesOk(): bool {
        if ( class_exists( 'GFAPI' ) ) {
            return true;
        }

        add_action( 'admin_notices', function (): void {
            echo '<div class="notice notice-error"><p>'
                . '<strong>SPA Register GF:</strong> Gravity Forms nie je aktívny. Plugin sa nespustil.'
                . '</p></div>';
        } );

        return false;
    }

    /**
     * Session štartujeme LEN keď to dáva zmysel:
     * - na FE pri zobrazení /register (tvoj register flow),
     * - alebo pri našich debug ajax akciách.
     *
     * Nespúšťame session globálne na všetkých requestoch,
     * ani na cudzích admin-ajax requestoch.
     */
    public static function ensureSession(): void {
        if ( session_status() !== PHP_SESSION_NONE ) {
            return;
        }

        if ( headers_sent() ) {
            return;
        }

        // Ak je to admin-ajax, session spustíme len pre naše debug akcie
        if ( defined('DOING_AJAX') && DOING_AJAX ) {
            $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
            $allowed = [
                'spa_debug_constants',
                'spa_check_session',
                // 'spa_test_session', // ÚMYSELNE VYPNUTÉ – nebudeme vytvárať test session
            ];

            if ( ! in_array( $action, $allowed, true ) ) {
                return;
            }
        }

        // Inak (bežný FE request) session povolíme – plugin ju potrebuje na /register
        session_start();

        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( 'SPA-REGISTER-GF: Session started | ID: ' . session_id() );
        }
    }

    private static function wireHooks(): void {

        /**
         * Session init – ale cez ensureSession() s podmienkami vyššie.
         * Dáme to veľmi skoro.
         */
        add_action( 'init', [ self::class, 'ensureSession' ], 1 );

        /**
         * DEBUG endpointy: len keď WP_DEBUG a len pre admina.
         * (Zabránime tomu, aby si testovaním „vyrábal“ session a maskoval chybu flow.)
         */
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            add_action( 'wp_ajax_spa_debug_constants', [ self::class, 'debugConstants' ] );
            add_action( 'wp_ajax_spa_check_session',   [ self::class, 'checkSession' ] );
        }

        // GF hooky (bez form ID – guard má byť v Hooks triedach cez cssClass spa-register-gf)
        $preRender  = new \SpaRegisterGf\Hooks\PreRenderHooks();
        $validation = new \SpaRegisterGf\Hooks\ValidationHooks();
        $submission = new \SpaRegisterGf\Hooks\SubmissionHooks();

        add_filter( 'gform_pre_render',       [ $preRender,  'handle' ],               10, 1 );
        add_filter( 'gform_pre_validation',   [ $validation, 'handlePreValidation' ],  10, 1 );
        add_filter( 'gform_validation',       [ $validation, 'handleValidation' ],     10, 1 );
        add_action( 'gform_after_submission', [ $submission, 'handle' ],               10, 2 );

        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( 'SPA-REGISTER-GF: Hooks wired successfully' );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // DEBUG ENDPOINTY (len WP_DEBUG + len wp_ajax)
    // ─────────────────────────────────────────────────────────────

    public static function debugConstants(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error([ 'message' => 'Forbidden' ], 403);
        }

        wp_send_json( [
            'SPA_REG_GF_VERSION'      => defined('SPA_REG_GF_VERSION') ? SPA_REG_GF_VERSION : 'NOT_DEFINED',
            'SPA_REG_GF_SESSION_KEY'  => defined('SPA_REG_GF_SESSION_KEY') ? SPA_REG_GF_SESSION_KEY : 'NOT_DEFINED',
            'SPA_REG_GF_SESSION_TTL'  => defined('SPA_REG_GF_SESSION_TTL') ? SPA_REG_GF_SESSION_TTL : 'NOT_DEFINED',
            'SPA_REG_GF_CSS_CLASS'    => defined('SPA_REG_GF_CSS_CLASS') ? SPA_REG_GF_CSS_CLASS : 'NOT_DEFINED',
            'session_status'          => session_status(),
            'session_id'              => session_id(),
            'booted'                  => self::$booted,
        ] );
    }

    public static function checkSession(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error([ 'message' => 'Forbidden' ], 403);
        }

        self::ensureSession();

        $data = $_SESSION['spa_registration'] ?? null;

        wp_send_json( [
            'exists'       => isset( $_SESSION['spa_registration'] ),
            'spa_registration' => $data,
            'session_id'   => session_id(),
            'status'       => session_status(),
        ] );
    }
}
