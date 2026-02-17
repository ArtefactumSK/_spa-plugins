<?php
namespace SpaRegisterGf\Services;

use SpaRegisterGf\Domain\RegistrationPayload;
use SpaRegisterGf\Infrastructure\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vytvára CPT spa_registration a zapisuje postmeta.
 * Meta kľúče výhradne podľa SPA-DATA-MAP.md.
 * post_status je vždy 'pending'.
 */
class RegistrationService {

    public function create(
        RegistrationPayload $payload,
        array               $userIds,
        SessionService      $session
    ): int {
        $programId    = $session->getProgramId();
        $clientUserId = $userIds['client_user_id'] ?? 0;

        if ( $programId <= 0 || $clientUserId <= 0 ) {
            Logger::error( 'registration_service_missing_ids', [
                'program_id'    => $programId,
                'client_user_id'=> $clientUserId,
            ] );
            throw new \RuntimeException( 'Chýbajú povinné ID pre registráciu.' );
        }

        // Názov programu pre post_title
        $program  = get_post( $programId );
        $client   = get_userdata( $clientUserId );
        $title    = ( $client ? trim( $client->first_name . ' ' . $client->last_name ) : 'Neznámy' )
                    . ' – '
                    . ( $program ? $program->post_title : 'Program #' . $programId );

        // Reuse témy ak existuje
        if ( function_exists( 'spa_create_registration' ) ) {
            $registrationId = spa_create_registration(
                $clientUserId,
                $programId,
                $userIds['parent_user_id'] ?? null,
                $payload->gfEntryId
            );
        } else {
            $registrationId = wp_insert_post( [
                'post_type'   => 'spa_registration',
                'post_status' => 'pending',
                'post_title'  => sanitize_text_field( $title ),
            ] );
        }

        if ( ! $registrationId || is_wp_error( $registrationId ) ) {
            Logger::error( 'registration_service_insert_failed', [
                'title' => $title,
            ] );
            throw new \RuntimeException( 'Nepodarilo sa vytvoriť registráciu.' );
        }

        $this->saveMeta( $registrationId, $payload, $userIds, $session );

        Logger::info( 'registration_created', [
            'registration_id' => $registrationId,
            'scope'           => $session->getScope(),
            'program_id'      => $programId,
            'client_user_id'  => $clientUserId,
        ] );

        return (int) $registrationId;
    }

    // ── Zápis postmeta ───────────────────────────────────────────────────────
    // Kľúče výhradne podľa SPA-DATA-MAP.md (sekcia spa_registration)

    private function saveMeta(
        int                $id,
        RegistrationPayload $payload,
        array              $userIds,
        SessionService     $session
    ): void {
        update_post_meta( $id, 'program_id',          $session->getProgramId() );
        update_post_meta( $id, 'client_user_id',      $userIds['client_user_id'] );
        update_post_meta( $id, 'parent_user_id',      $userIds['parent_user_id'] ?? '' );
        update_post_meta( $id, 'status',              'pending' );
        update_post_meta( $id, 'frequency_key',       $session->getFrequencyKey() );
        update_post_meta( $id, 'amount',              $session->getAmount() );
        update_post_meta( $id, 'external_surcharge',  $session->getExternalSurcharge() ?? '' );
        update_post_meta( $id, 'gf_entry_id',         $payload->gfEntryId );
        update_post_meta( $id, 'scope',               $session->getScope() );

        // Generovanie VS – reuse témy ak existuje
        $vs = function_exists( 'spa_generate_vs' )
            ? spa_generate_vs()
            : $this->generateVs();

        update_post_meta( $id, 'spa_vs', $vs );
    }

    /**
     * Fallback generátor variabilného symbolu (3-miestny unikátny kód).
     * Ak téma poskytuje spa_generate_vs(), tento sa nepoužije.
     */
    private function generateVs(): string {
        do {
            $vs = str_pad( (string) wp_rand( 100, 999 ), 3, '0', STR_PAD_LEFT );
            $existing = get_posts( [
                'post_type'   => 'spa_registration',
                'meta_key'    => 'spa_vs',
                'meta_value'  => $vs,
                'numberposts' => 1,
                'fields'      => 'ids',
            ] );
        } while ( ! empty( $existing ) );

        return $vs;
    }
}