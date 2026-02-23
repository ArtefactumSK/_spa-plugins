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
        // Guard — session spúšťame len ak je to relevantné
        $is_register_page = isset( $_SERVER['REQUEST_URI'] )
            && str_contains( $_SERVER['REQUEST_URI'], 'register' );
        $is_gf_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX
            && isset( $_POST['gform_submit'] );
        $is_spa_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX
            && isset( $_REQUEST['action'] )
            && str_starts_with( (string) $_REQUEST['action'], 'spa_' );

        if ( ! $is_register_page && ! $is_gf_ajax && ! $is_spa_ajax ) {
            return;
        }

        if ( session_status() !== PHP_SESSION_NONE ) {
            return;
        }

        if ( headers_sent( $file, $line ) ) {
            Logger::warning( 'session_headers_already_sent', [
                'file' => $file,
                'line' => $line,
            ] );
            return;
        }

        session_start();

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'SPA-REGISTER-GF: Session started | ID: ' . session_id() );
        }
    }

    private static function wireHooks(): void {

        /**
         * Session init – ale cez ensureSession() s podmienkami vyššie.
         * Dáme to veľmi skoro.
         */
        add_action( 'plugins_loaded', [ self::class, 'ensureSession' ], 1 );
        add_action( 'init',           [ self::class, 'ensureSession' ], 1 );

        /**
         * DEBUG endpointy: len keď WP_DEBUG a len pre admina.
         * (Zabránime tomu, aby si testovaním „vyrábal“ session a maskoval chybu flow.)
         */
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            add_action( 'wp_ajax_spa_debug_constants', [ self::class, 'debugConstants' ] );
            add_action( 'wp_ajax_spa_check_session',   [ self::class, 'checkSession' ] );
        }

        // GF hooky (bez form ID – guard má byť v Hooks triedach cez cssClass spa-register-gf)
        $enqueue     = new \SpaRegisterGf\Hooks\EnqueueHooks();
        $preRender   = new \SpaRegisterGf\Hooks\PreRenderHooks();
        $validation  = new \SpaRegisterGf\Hooks\ValidationHooks();
        $submission  = new \SpaRegisterGf\Hooks\SubmissionHooks();

        $enqueue->register();

        add_filter( 'gform_pre_render',       [ $preRender,  'handle' ],               10, 1 );
        add_filter( 'gform_pre_validation',   [ $validation, 'handlePreValidation' ],  10, 1 );
        add_filter( 'gform_validation',       [ $validation, 'handleValidation' ],     10, 1 );
        add_action( 'gform_after_submission', [ $submission, 'handle' ],               10, 2 );

        /**
         * PIN pre dieťa – server-side po vytvorení WP používateľa.
         * Používame spa_after_child_created (nie user_register), pretože user_register
         * sa volá pred nastavením roly spa_child. spa_after_child_created garantuje,
         * že používateľ má rolu spa_child.
         */
        add_action( 'spa_after_child_created', [ self::class, 'assignChildPin' ], 5, 1 );

        /**
         * Záloha pre používateľov vytvorených mimo spa-register-gf (napr. import).
         * user_register sa volá po wp_insert_user – vtedy už má user rolu (ak bola
         * nastavená v rámci toho istého volania). Pre istotu kontrolujeme rolu.
         */
        add_action( 'user_register', [ self::class, 'assignChildPinOnRegister' ], 20, 1 );

        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( 'SPA-REGISTER-GF: Hooks wired successfully' );
        }
    }

    /**
     * Priradí 4-miestny PIN používateľovi s rolou spa_child.
     * Volané z spa_after_child_created (priorita 5 – pred spa_auto_assign_vs_and_pin).
     */
    public static function assignChildPin( int $user_id ): void {
        $user = get_userdata( $user_id );
        if ( ! $user || ! is_array( $user->roles ) ) {
            return;
        }

        // PIN iba pre spa_child (nie spa_client/adult)
        if ( ! in_array( 'spa_child', $user->roles, true ) ) {
            return;
        }

        self::doAssignPin( $user_id );
    }

    /**
     * Záloha: priradí PIN pri user_register, ak user má spa_child rolu.
     * (Niektoré flow nastavujú rolu pri vytvorení.)
     */
    public static function assignChildPinOnRegister( int $user_id ): void {
        $user = get_userdata( $user_id );
        if ( ! $user || ! is_array( $user->roles ) ) {
            return;
        }

        if ( ! in_array( 'spa_child', $user->roles, true ) ) {
            return;
        }

        // Ak už má PIN (napr. z spa_after_child_created), netreba
        if ( get_user_meta( $user_id, 'spa_pin', true ) ) {
            return;
        }

        self::doAssignPin( $user_id );
    }

    /**
     * Vygeneruje unikátny PIN a uloží do usermeta.
     * spa_pin_plain (4-miestny), spa_pin (hash).
     */
    private static function doAssignPin( int $user_id ): void {
        global $wpdb;

        $pin_plain = null;
        $max_attempts = 20;

        for ( $i = 0; $i < $max_attempts; $i++ ) {
            $candidate = str_pad( (string) wp_rand( 0, 9999 ), 4, '0', STR_PAD_LEFT );

            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} 
                 WHERE meta_key = 'spa_pin_plain' AND meta_value = %s AND user_id != %d",
                $candidate,
                $user_id
            ) );

            if ( (int) $exists === 0 ) {
                $pin_plain = $candidate;
                break;
            }
        }

        if ( $pin_plain === null ) {
            $pin_plain = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
        }

        update_user_meta( $user_id, 'spa_pin_plain', $pin_plain );
        update_user_meta( $user_id, 'spa_pin', wp_hash_password( $pin_plain ) );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[spa-register-gf] PIN assigned user=' . $user_id . ' pin=' . $pin_plain );
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
