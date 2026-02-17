<?php
namespace SpaRegisterGf\Services;

use SpaRegisterGf\Domain\RegistrationPayload;
use SpaRegisterGf\Infrastructure\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vytvára / aktualizuje adult klienta pre scope 'adult'.
 * Meta kľúče výhradne podľa SPA-DATA-MAP.md.
 */
class UserCreationAdultHelper {

    public function create( RegistrationPayload $p ): array {
        $email = $p->clientEmailRequired ?? $p->clientEmail;

        if ( function_exists( 'spa_get_or_create_client' ) ) {
            $clientId = spa_get_or_create_client(
                $email,
                $p->memberFirstName,
                $p->memberLastName,
                $p->clientPhone,
                $p->memberBirthdate
            );
        } else {
            $clientId = $this->createOrUpdateClient( $p, $email );
        }

        if ( ! $clientId || is_wp_error( $clientId ) ) {
            Logger::error( 'adult_helper_client_failed', [ 'email' => $email ] );
            throw new \RuntimeException( 'Nepodarilo sa vytvoriť klienta.' );
        }

        return [
            'client_user_id' => (int) $clientId,
        ];
    }

    private function createOrUpdateClient( RegistrationPayload $p, ?string $email ): int {
        if ( empty( $email ) ) {
            return 0;
        }

        $existing = get_user_by( 'email', $email );

        if ( $existing ) {
            update_user_meta( $existing->ID, 'phone',            $p->clientPhone );
            update_user_meta( $existing->ID, 'birthdate',        $p->memberBirthdate );
            update_user_meta( $existing->ID, 'address_street',   $p->clientAddressStreet );
            update_user_meta( $existing->ID, 'address_psc',      $p->clientAddressPostcode );
            update_user_meta( $existing->ID, 'address_city',     $p->clientAddressCity );

            if ( ! empty( $p->memberHealthRestrictions ) ) {
                update_user_meta( $existing->ID, 'health_notes', $p->memberHealthRestrictions );
            }

            return $existing->ID;
        }

        $username = $this->resolveUsername( $p->memberFirstName, $p->memberLastName );
        $password = wp_generate_password( 12, true );
        $userId   = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $userId ) ) {
            return 0;
        }

        wp_update_user( [ 'ID' => $userId, 'role' => 'spa_client' ] );

        // Meta kľúče výhradne podľa SPA-DATA-MAP.md
        update_user_meta( $userId, 'first_name',      $p->memberFirstName );
        update_user_meta( $userId, 'last_name',       $p->memberLastName );
        update_user_meta( $userId, 'phone',           $p->clientPhone );
        update_user_meta( $userId, 'birthdate',       $p->memberBirthdate );
        update_user_meta( $userId, 'address_street',  $p->clientAddressStreet );
        update_user_meta( $userId, 'address_psc',     $p->clientAddressPostcode );
        update_user_meta( $userId, 'address_city',    $p->clientAddressCity );

        if ( ! empty( $p->memberHealthRestrictions ) ) {
            update_user_meta( $userId, 'health_notes', $p->memberHealthRestrictions );
        }

        do_action( 'spa_after_client_created', $userId );

        return $userId;
    }

    /**
     * Rovnaká stratégia ako pri child: meno.priezvisko(+index)
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