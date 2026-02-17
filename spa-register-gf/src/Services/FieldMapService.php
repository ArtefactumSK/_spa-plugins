<?php
namespace SpaRegisterGf\Services;

use SpaRegisterGf\Infrastructure\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Načíta fields.json a prekladá logical key → GF field identifikátor.
 * Žiadny kód v plugine nesmie použiť GF field identifikátor priamo.
 */
class FieldMapService {

    private static ?array $map = null;

    // ── Načítanie mapy ───────────────────────────────────────────────────────

    private static function load(): void {
        if ( self::$map !== null ) {
            return;
        }

        $path = SPA_REG_GF_DIR . 'spa-config/fields.json';

        if ( ! file_exists( $path ) ) {
            Logger::error( 'fieldmap_file_missing', [ 'path' => $path ] );
            self::$map = [];
            return;
        }

        $json = file_get_contents( $path );
        $decoded = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            Logger::error( 'fieldmap_json_invalid', [ 'error' => json_last_error_msg() ] );
            self::$map = [];
            return;
        }

        self::$map = $decoded;
    }

    // ── Verejné API ──────────────────────────────────────────────────────────

    /**
     * Vráti GF field identifikátor pre daný logical key.
     * Ak logical key neexistuje, vyhodí výnimku.
     *
     * @throws \InvalidArgumentException
     */
    public static function resolve( string $logicalKey ): string {
        self::load();

        if ( ! isset( self::$map[ $logicalKey ] ) ) {
            Logger::error( 'fieldmap_key_not_found', [ 'key' => $logicalKey ] );
            throw new \InvalidArgumentException( "Logical key '$logicalKey' neexistuje v fields.json" );
        }

        return (string) self::$map[ $logicalKey ];
    }

    /**
     * Bezpečná verzia resolve() – vracia null ak key neexistuje.
     */
    public static function tryResolve( string $logicalKey ): ?string {
        self::load();
        return isset( self::$map[ $logicalKey ] )
            ? (string) self::$map[ $logicalKey ]
            : null;
    }

    /**
     * Vráti celú mapu (pre debugging / iteráciu).
     */
    public static function getAll(): array {
        self::load();
        return self::$map ?? [];
    }

    /**
     * Resetuje cache (pre testy).
     */
    public static function reset(): void {
        self::$map = null;
    }
}