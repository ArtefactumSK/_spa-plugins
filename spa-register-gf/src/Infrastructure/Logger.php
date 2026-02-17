<?php
namespace SpaRegisterGf\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Logger {

    public static function info( string $event, array $context = [] ): void {
        self::write( 'INFO', $event, $context );
    }

    public static function warning( string $event, array $context = [] ): void {
        self::write( 'WARNING', $event, $context );
    }

    public static function error( string $event, array $context = [] ): void {
        self::write( 'ERROR', $event, $context );
    }

    private static function write( string $level, string $event, array $context ): void {
        $msg = sprintf(
            'SPA_REG_GF [%s] %s | %s',
            $level,
            $event,
            wp_json_encode( $context )
        );

        // Reuse témy ak existuje, inak error_log
        if ( function_exists( 'spa_log' ) ) {
            spa_log( $event, $context );
        } else {
            error_log( $msg );
        }
    }
}