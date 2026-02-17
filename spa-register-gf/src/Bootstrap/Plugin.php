<?php

namespace SpaRegisterGf\Bootstrap;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    private static bool $booted = false;

    public static function boot(): void {
        error_log('SPA REGISTER GF SESSION: ' . print_r($_SESSION['spa_registration'] ?? 'NO SESSION', true));

        if ( self::$booted ) {
            return;
        }
        self::$booted = true;

        self::registerAutoload();
        self::checkDependencies();
        self::wireHooks();
    }

    // ── Autoload ────────────────────────────────────────────────────────────

    private static function registerAutoload(): void {
        spl_autoload_register( function ( string $class ): void {
            // Namespace prefix: SpaRegisterGf\
            $prefix = 'SpaRegisterGf\\';
            $baseDir = SPA_REG_GF_DIR . 'src/';

            if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
                return;
            }

            // SpaRegisterGf\Services\SessionService → src/Services/SessionService.php
            $relative = substr( $class, strlen( $prefix ) );
            $file     = $baseDir . str_replace( '\\', '/', $relative ) . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        } );
    }

    // ── Dependency guard ────────────────────────────────────────────────────

    private static function checkDependencies(): void {
        if ( ! class_exists( 'GFAPI' ) ) {
            add_action( 'admin_notices', function (): void {
                echo '<div class="notice notice-error"><p>'
                    . '<strong>SPA Register GF:</strong> Gravity Forms nie je aktívny. Plugin je neaktívny.'
                    . '</p></div>';
            } );
            self::$booted = false; // Prerušíme ďalší init
            return;
        }
    }

    // ── Hook wiring ─────────────────────────────────────────────────────────

    private static function wireHooks(): void {
        if ( ! self::$booted ) {
            return;
        }

        // Inicializácia session skôr ako GF začne render
        add_action( 'init', [ self::class, 'initSession' ], 1 );

        // GF hooky – priority sú zámerné (10 = pred GF vlastnou logikou)
        $preRender   = new \SpaRegisterGf\Hooks\PreRenderHooks();
        $validation  = new \SpaRegisterGf\Hooks\ValidationHooks();
        $submission  = new \SpaRegisterGf\Hooks\SubmissionHooks();

        add_filter( 'gform_pre_render',      [ $preRender,  'handle' ],          10, 1 );
        add_filter( 'gform_pre_validation',  [ $validation, 'handlePreValidation' ], 10, 1 );
        add_filter( 'gform_validation',      [ $validation, 'handleValidation' ], 10, 1 );
        add_action( 'gform_after_submission',[ $submission, 'handle' ],           10, 2 );
    }

    public static function initSession(): void {
        if ( session_status() === PHP_SESSION_NONE && ! headers_sent() ) {
            session_start();
        }
    }
}