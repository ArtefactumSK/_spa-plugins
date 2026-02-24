<?php
namespace SpaRegisterGf\Services;

use SpaRegisterGf\Config\SpaConfig;
use SpaRegisterGf\Domain\RegistrationPayload;
use SpaRegisterGf\Infrastructure\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vytvára parent + child userov pre scope 'child'.
 * Meta kľúče výhradne podľa SPA-DATA-MAP.md.
 *
 * Login stratégia:
 *   meno.priezvisko → meno.priezvisko1 → meno.priezvisko2 ...
 *   Email NIE JE login.
 */
class UserCreationChildHelper {

    public function create( RegistrationPayload $p ): array {
        // 1. Rodič
        if ( function_exists( 'spa_get_or_create_parent' ) ) {
            $parentId = spa_get_or_create_parent(
                $p->parentEmail,
                $p->guardianFirstName,
                $p->guardianLastName,
                $p->parentPhone,
                '',  // address_street (child formulár túto adresu nemá)
                '',
                ''
            );
        } else {
            $parentId = $this->createOrUpdateParent( $p );
        }

        if ( ! $parentId || is_wp_error( $parentId ) ) {
            Logger::error( 'child_helper_parent_failed', [ 'email' => $p->parentEmail ] );
            throw new \RuntimeException( 'Nepodarilo sa vytvoriť rodiča.' );
        }

        // 2. Dieťa
        if ( function_exists( 'spa_create_child_account' ) ) {
            $childId = spa_create_child_account(
                $p->memberFirstName,
                $p->memberLastName,
                $p->memberBirthdate,
                $parentId,
                $p->memberHealthRestrictions,
                $p->memberBirthnumber
            );
        } else {
            $childId = $this->createChild( $p, $parentId );
        }

        if ( ! $childId || is_wp_error( $childId ) ) {
            Logger::error( 'child_helper_child_failed', [ 'parent_id' => $parentId ] );
            throw new \RuntimeException( 'Nepodarilo sa vytvoriť dieťa.' );
        }

        if ( ! empty( $p->memberHealthRestrictions ) ) {
            update_user_meta(
                $childId,
                'spa_health_restrictions',
                sanitize_textarea_field( $p->memberHealthRestrictions )
            );
        }

        return [
            'parent_user_id' => (int) $parentId,
            'child_user_id'  => (int) $childId,
            'client_user_id' => (int) $childId,  // alias pre RegistrationService
        ];
    }

    // ── Vlastná implementácia ak téma helper neexistuje ──────────────────────

    private function createOrUpdateParent( RegistrationPayload $p ): int {
        $existing = get_user_by( 'email', $p->parentEmail );

        if ( $existing ) {
            // Aktualizuj meta
            update_user_meta( $existing->ID, 'first_name', $p->guardianFirstName );
            update_user_meta( $existing->ID, 'last_name',  $p->guardianLastName );
            update_user_meta( $existing->ID, 'phone',      $p->parentPhone );
            return $existing->ID;
        }

        $username = $this->resolveUsername( $p->guardianFirstName, $p->guardianLastName );
        $password = wp_generate_password( 12, true );
        $userId   = wp_create_user( $username, $password, $p->parentEmail );

        if ( is_wp_error( $userId ) ) {
            return 0;
        }

        wp_update_user( [ 'ID' => $userId, 'role' => 'spa_parent' ] );
        update_user_meta( $userId, 'first_name', $p->guardianFirstName );
        update_user_meta( $userId, 'last_name',  $p->guardianLastName );
        update_user_meta( $userId, 'phone',      $p->parentPhone );

        return $userId;
    }

    private function createChild( RegistrationPayload $p, int $parentId ): int {
        $username = $this->resolveUsername( $p->memberFirstName, $p->memberLastName );
        $password = wp_generate_password( 12, true );
        $child_domain = SpaConfig::CHILD_EMAIL_DOMAIN;
        $first        = sanitize_title( $p->memberFirstName );
        $last         = sanitize_title( $p->memberLastName );
        $base         = strtolower( $first . '.' . $last );
        $email        = $base . '@' . $child_domain;
        $i            = 1;
        while ( email_exists( $email ) ) {
            $email = $base . $i . '@' . $child_domain;
            $i++;
        }
        $userId = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $userId ) ) {
            return 0;
        }

        wp_update_user( [ 'ID' => $userId, 'role' => 'spa_child' ] );

        // Meta kľúče výhradne podľa SPA-DATA-MAP.md
        update_user_meta( $userId, 'first_name', $p->memberFirstName );
        update_user_meta( $userId, 'last_name',  $p->memberLastName );
        update_user_meta( $userId, 'birthdate',  $p->memberBirthdate );
        update_user_meta( $userId, 'parent_id',  $parentId );

        if ( ! empty( $p->memberHealthRestrictions ) ) {
            update_user_meta(
                $userId,
                'spa_health_restrictions',
                sanitize_textarea_field( $p->memberHealthRestrictions )
            );
        }

        if ( ! empty( $p->memberBirthnumber ) ) {
            $rc = preg_replace( '/[^0-9]/', '', $p->memberBirthnumber );
            update_user_meta( $userId, 'rodne_cislo', $rc );
        }

        do_action( 'spa_after_child_created', $userId );

        return $userId;
    }

    /**
     * Generuje username podľa stratégie: meno.priezvisko(+index)
     * Email NIE JE login.
     */
    private function resolveUsername( string $first, string $last ): string {
        $chars = [
            'á'=>'a','ä'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i',
            'ľ'=>'l','ĺ'=>'l','ň'=>'n','ó'=>'o','ô'=>'o','ŕ'=>'r','ř'=>'r',
            'š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
            'Á'=>'A','Ä'=>'A','Č'=>'C','Ď'=>'D','É'=>'E','Ě'=>'E','Í'=>'I',
            'Ľ'=>'L','Ĺ'=>'L','Ň'=>'N','Ó'=>'O','Ô'=>'O','Ŕ'=>'R','Ř'=>'R',
            'Š'=>'S','Ť'=>'T','Ú'=>'U','Ů'=>'U','Ý'=>'Y','Ž'=>'Z',
        ];

        $f = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', strtr( $first, $chars ) ) );
        $l = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', strtr( $last,  $chars ) ) );

        $base     = substr( $f . '.' . $l, 0, 50 );
        $username = $base;
        $i        = 1;

        while ( username_exists( $username ) ) {
            $username = $base . $i;
            $i++;
        }

        return $username;
    }
}