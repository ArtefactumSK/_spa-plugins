<?php
namespace SpaRegisterGf\Services;

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
    private const DEFAULT_COUNTRY = 'Slovensko';

    public function create( RegistrationPayload $p ): array {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log(
                '[spa-register-gf] parent_address_payload: '
                . wp_json_encode( [
                    'street' => (string) $p->clientAddressStreet,
                    'psc' => (string) $p->clientAddressPostcode,
                    'city' => (string) $p->clientAddressCity,
                    'country' => (string) $p->clientAddressCountry,
                ] )
            );
        }

        // 1. Rodič
        if ( function_exists( 'spa_get_or_create_parent' ) ) {
            $existingParent = get_user_by( 'email', sanitize_email( (string) $p->parentEmail ) );
            if ( $existingParent && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[spa-register-gf] existing_parent_found: ' . (int) $existingParent->ID );
            }
            $parentId = spa_get_or_create_parent(
                $p->parentEmail,
                $p->guardianFirstName,
                $p->guardianLastName,
                $p->parentPhone,
                (string) $p->clientAddressStreet,
                (string) $p->clientAddressPostcode,
                (string) $p->clientAddressCity
            );
            if ( ! $existingParent && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[spa-register-gf] created_new_parent: ' . (int) $parentId );
            }
        } else {
            $parentId = $this->createOrUpdateParent( $p );
        }

        if ( ! $parentId || is_wp_error( $parentId ) ) {
            Logger::error( 'child_helper_parent_failed', [ 'email' => $p->parentEmail ] );
            throw new \RuntimeException( 'Nepodarilo sa vytvoriť rodiča.' );
        }

        // 2. Dieťa: najprv lookup podľa rodného čísla, až potom create.
        $existingChildId = $this->findExistingChildIdByBirthnumber( $p->memberBirthnumber );
        if ( $existingChildId > 0 ) {
            $childId = $existingChildId;
            Logger::info( 'child_identity_reused', [ 'child_user_id' => $childId ] );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[spa-register-gf] existing_child_found: ' . (int) $childId );
            }

            update_user_meta( $childId, 'first_name', $p->memberFirstName );
            update_user_meta( $childId, 'last_name',  $p->memberLastName );
            update_user_meta( $childId, 'birthdate',  $p->memberBirthdate );
            update_user_meta( $childId, 'parent_id',  $parentId );
        } elseif ( function_exists( 'spa_create_child_account' ) ) {
            Logger::warning( 'child_identity_lookup_miss_new_user', [
                'birthnumber' => (string) $p->memberBirthnumber,
            ] );
            $childId = spa_create_child_account(
                $p->memberFirstName,
                $p->memberLastName,
                $p->memberBirthdate,
                $parentId,
                $p->memberHealthRestrictions,
                $p->memberBirthnumber
            );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[spa-register-gf] created_new_child: ' . (int) $childId );
            }
        } else {
            Logger::warning( 'child_identity_lookup_miss_new_user', [
                'birthnumber' => (string) $p->memberBirthnumber,
            ] );
            $childId = $this->createChild( $p, $parentId );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[spa-register-gf] created_new_child: ' . (int) $childId );
            }
        }

        if ( ! $childId || is_wp_error( $childId ) ) {
            Logger::error( 'child_helper_child_failed', [ 'parent_id' => $parentId ] );
            throw new \RuntimeException( 'Nepodarilo sa vytvoriť dieťa.' );
        }

        if ( ! empty( $p->memberHealthRestrictions ) ) {
            // Pôvodný meta kľúč pre spätnú kompatibilitu
            update_user_meta(
                $childId,
                'spa_health_restrictions',
                sanitize_textarea_field( $p->memberHealthRestrictions )
            );

            // Nový zdroj pravdy – usermeta spa_health_notes
            update_user_meta(
                $childId,
                'spa_health_notes',
                sanitize_textarea_field( $p->memberHealthRestrictions )
            );
        }

        update_user_meta( $parentId, 'consent_marketing', $p->consentMarketing ? 1 : 0 );
        update_user_meta( $childId, 'address_country', $this->normalizeCountry( $p->clientAddressCountry ) );

        $this->updateUserMetaIfNotEmpty( (int) $parentId, 'address_street', (string) $p->clientAddressStreet );
        $this->updateUserMetaIfNotEmpty( (int) $parentId, 'address_psc', (string) $p->clientAddressPostcode );
        $this->updateUserMetaIfNotEmpty( (int) $parentId, 'address_city', (string) $p->clientAddressCity );
        $this->updateUserMetaIfNotEmpty( (int) $parentId, 'address_country', $this->normalizeCountry( $p->clientAddressCountry ) );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log(
                '[spa-register-gf] parent_address_saved: '
                . wp_json_encode( [
                    'parent_user_id' => (int) $parentId,
                    'address_street' => (string) get_user_meta( (int) $parentId, 'address_street', true ),
                    'address_psc' => (string) get_user_meta( (int) $parentId, 'address_psc', true ),
                    'address_city' => (string) get_user_meta( (int) $parentId, 'address_city', true ),
                    'address_country' => (string) get_user_meta( (int) $parentId, 'address_country', true ),
                ] )
            );
        }

        return [
            'parent_user_id' => (int) $parentId,
            'child_user_id'  => (int) $childId,
            'client_user_id' => (int) $childId,  // alias pre RegistrationService
        ];
    }

    private function findExistingChildIdByBirthnumber( ?string $birthnumber ): int {
        $rc = preg_replace( '/[^0-9]/', '', (string) $birthnumber );
        if ( $rc === '' ) {
            return 0;
        }

        $values = [ $rc ];
        if ( strlen( $rc ) > 6 ) {
            $values[] = substr( $rc, 0, 6 ) . '/' . substr( $rc, 6 );
        }

        $metaQuery = [
            'relation' => 'OR',
            [
                'key'   => 'rodne_cislo',
                'value' => $values[0],
            ],
        ];
        if ( isset( $values[1] ) ) {
            $metaQuery[] = [
                'key'   => 'rodne_cislo',
                'value' => $values[1],
            ];
        }

        $query = new \WP_User_Query( [
            'number'     => 1,
            'count_total'=> false,
            'fields'     => 'ID',
            'meta_query' => $metaQuery,
        ] );

        $ids = $query->get_results();
        if ( empty( $ids ) ) {
            return 0;
        }

        return (int) $ids[0];
    }

    // ── Vlastná implementácia ak téma helper neexistuje ──────────────────────

    private function createOrUpdateParent( RegistrationPayload $p ): int {
        $existing = get_user_by( 'email', $p->parentEmail );

        if ( $existing ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[spa-register-gf] existing_parent_found: ' . (int) $existing->ID );
            }
            // Aktualizuj meta
            update_user_meta( $existing->ID, 'first_name', $p->guardianFirstName );
            update_user_meta( $existing->ID, 'last_name',  $p->guardianLastName );
            update_user_meta( $existing->ID, 'phone',      $p->parentPhone );
            update_user_meta( $existing->ID, 'consent_marketing', $p->consentMarketing ? 1 : 0 );
            $this->updateUserMetaIfNotEmpty( (int) $existing->ID, 'address_street', (string) $p->clientAddressStreet );
            $this->updateUserMetaIfNotEmpty( (int) $existing->ID, 'address_psc', (string) $p->clientAddressPostcode );
            $this->updateUserMetaIfNotEmpty( (int) $existing->ID, 'address_city', (string) $p->clientAddressCity );
            $this->updateUserMetaIfNotEmpty( (int) $existing->ID, 'address_country', $this->normalizeCountry( $p->clientAddressCountry ) );
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
        update_user_meta( $userId, 'consent_marketing', $p->consentMarketing ? 1 : 0 );
        $this->updateUserMetaIfNotEmpty( (int) $userId, 'address_street', (string) $p->clientAddressStreet );
        $this->updateUserMetaIfNotEmpty( (int) $userId, 'address_psc', (string) $p->clientAddressPostcode );
        $this->updateUserMetaIfNotEmpty( (int) $userId, 'address_city', (string) $p->clientAddressCity );
        $this->updateUserMetaIfNotEmpty( (int) $userId, 'address_country', $this->normalizeCountry( $p->clientAddressCountry ) );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[spa-register-gf] created_new_parent: ' . (int) $userId );
        }

        return $userId;
    }

    private function createChild( RegistrationPayload $p, int $parentId ): int {
        $username = $this->resolveUsername( $p->memberFirstName, $p->memberLastName );
        $password = wp_generate_password( 12, true );

        $domain = '';

        if ( class_exists( '\SpaSystem\Settings\SettingsService' ) ) {
            $domain = \SpaSystem\Settings\SettingsService::get( 'academy.domain', '' );
        }

        $child_domain = $domain;

        $first = sanitize_title( $p->memberFirstName );
        $last  = sanitize_title( $p->memberLastName );
        $base  = strtolower( $first . '.' . $last );
        $email = $base . '@' . $child_domain;
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

    private function normalizeCountry( ?string $country ): string {
        $value = sanitize_text_field( (string) $country );
        return $value !== '' ? $value : self::DEFAULT_COUNTRY;
    }

    private function updateUserMetaIfNotEmpty( int $userId, string $metaKey, string $value ): void {
        $trimmed = trim( $value );
        if ( $trimmed === '' ) {
            return;
        }
        update_user_meta( $userId, $metaKey, $trimmed );
    }
}
