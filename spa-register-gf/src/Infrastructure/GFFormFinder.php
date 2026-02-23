<?php
namespace SpaRegisterGf\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GFFormFinder {

    /**
     * Overí, či predaný $form patrí nášmu formuláru.
     * NIKDY nepoužíva form ID.
     * Guard výhradne cez CSS class definovanú v konštante SPA_REG_GF_CSS_CLASS.
     */
    public static function isSpaForm( array $form ): bool {
        if ( empty( $form['cssClass'] ) ) {
            return false;
        }
        $classes = array_map( 'trim', explode( ' ', $form['cssClass'] ) );
        return in_array( SPA_REG_GF_CSS_CLASS, $classes, true );
    }

    /**
     * Priamy guard pre hookuji – ak nie je SPA form, vráti false
     * a volajúci hook sa ukončí (return $arg bez zmeny).
     */
    public static function guard( array $form ): bool {
        $result = self::isSpaForm( $form );
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( '[spa-register-gf] GFFormFinder::guard | cssClass="' . ( $form['cssClass'] ?? '' ) . '" | const="' . ( defined('SPA_REG_GF_CSS_CLASS') ? SPA_REG_GF_CSS_CLASS : 'NOT_DEFINED' ) . '" | result=' . ( $result ? 'true' : 'false' ) );
        }
        return $result;
    }
}