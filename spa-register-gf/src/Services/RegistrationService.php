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
        $parentUserId = isset( $userIds['parent_user_id'] ) ? (int) $userIds['parent_user_id'] : 0;
        $gfEntryId    = (int) $payload->gfEntryId;
        $baseAmount   = (float) $session->getAmount();
        $surchargeRaw = $session->getExternalSurcharge();

        // Source of truth pre prvú platbu: PriceCalculatorService::finalAmount.
        $priceCalculator = new PriceCalculatorService();
        $priceCalc       = $priceCalculator->calculate( $session );
        $finalAmount     = (float) $priceCalc['finalAmount'];

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            spa_debug_log(
                '[spa-register-gf] registration_amount_debug: '
                . 'base_amount=' . $baseAmount
                . ' | external_surcharge_raw=' . (string) $surchargeRaw
                . ' | final_amount=' . $finalAmount
            );
        }

        if ( $programId <= 0 || $clientUserId <= 0 ) {
            Logger::error( 'registration_service_missing_ids', [
                'program_id'    => $programId,
                'client_user_id'=> $clientUserId,
            ] );
            throw new \RuntimeException( 'Chýbajú povinné ID pre registráciu.' );
        }

        // Základná idempotencia: ak už existuje CPT pre daný GF entry, nepokračujeme.
        $existingRegistrationId = $this->findExistingRegistrationByEntryId( $gfEntryId );
        if ( $existingRegistrationId > 0 ) {
            $existingVs = (string) get_post_meta( $existingRegistrationId, 'spa_vs', true );
            $existingDbRegistrationId = (int) get_post_meta( $existingRegistrationId, 'db_registration_id', true );
            $resolvedDbRegistrationId = $this->ensureDbCptSync(
                $existingRegistrationId,
                $existingDbRegistrationId,
                $existingVs !== '' ? $existingVs : $vs,
                $payload,
                $session,
                $userIds,
                $finalAmount
            );
            $this->syncInvoiceUserMeta( $payload, $userIds );
            Logger::info( 'registration_idempotent_hit', [
                'registration_id' => $existingRegistrationId,
                'gf_entry_id' => $gfEntryId,
                'db_registration_id' => $resolvedDbRegistrationId,
            ] );
            return $existingRegistrationId;
        }

        // Názov programu pre post_title
        $program  = get_post( $programId );
        if ( function_exists( 'spa_maybe_update_program_status_by_date' ) ) {
            spa_maybe_update_program_status_by_date( $programId );
        }
        $isProgramAvailable = function_exists( 'spa_is_group_available_for_registration' )
            ? (bool) spa_is_group_available_for_registration( $programId )
            : ( $program && $program->post_type === 'spa_group' && $program->post_status === 'publish' );
        if ( ! $isProgramAvailable ) {
            Logger::warning( 'registration_program_inactive_or_unavailable', [
                'program_id' => $programId,
            ] );
            throw new \RuntimeException( 'Vybraný program nie je dostupný pre registráciu.' );
        }

        $client   = get_userdata( $clientUserId );
        $title    = ( $client ? trim( $client->first_name . ' ' . $client->last_name ) : 'Neznámy' )
                    . ' – '
                    . ( $program ? $program->post_title : 'Program #' . $programId );

        // Generovanie VS – reuse témy ak existuje.
        $vs = function_exists( 'spa_generate_vs' )
            ? spa_generate_vs()
            : $this->generateVs();

        // 1) DB INSERT (doplnkovy krok) – nikdy nesmie blokovat CPT flow.
        $dbRegistrationId = 0;
        try {
            Logger::info( 'registration_db_insert_start', [
                'program_id' => $programId,
                'client_user_id' => $clientUserId,
                'parent_user_id' => $parentUserId,
                'gf_entry_id' => $gfEntryId,
            ] );
            $dbRegistrationId = (int) $this->insertRegistrationToDb(
                $clientUserId,
                $parentUserId,
                $programId,
                'pending',
                $finalAmount,
                (string) $session->getFrequencyKey(),
                (string) $vs,
                (string) ( $payload->paymentMethod ?? '' ),
                (int) $payload->invoiceToCompany,
                (int) $payload->invoiceAddressDifferent,
                (string) ( $payload->companyName ?? '' ),
                (string) ( $payload->companyIco ?? '' ),
                (string) ( $payload->companyDic ?? '' ),
                (string) ( $payload->companyIcdph ?? '' ),
                (string) ( $payload->companyAddressStreet ?? '' ),
                (string) ( $payload->companyAddressCity ?? '' ),
                (string) ( $payload->companyAddressPostcode ?? '' ),
                (string) ( $payload->companyAddressCountry ?? '' )
            );
        } catch ( \Throwable $e ) {
            Logger::error( 'registration_db_insert_exception', [
                'program_id' => $programId,
                'client_user_id' => $clientUserId,
                'gf_entry_id' => $gfEntryId,
                'message' => $e->getMessage(),
            ] );
            $dbRegistrationId = 0;
        }

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

        Logger::info( 'registration_cpt_create_success', [
            'registration_id' => (int) $registrationId,
            'gf_entry_id' => $gfEntryId,
        ] );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if (defined('SPA_DEBUG') && SPA_DEBUG === true) {
                spa_debug_log( 'SPA-REGISTER-GF: CPT CREATE success id=' . (int) $registrationId );
            }
            spa_debug_log( '[spa-register-gf] registration_saved_amount: ' . $finalAmount );
            spa_debug_log( '[spa-register-gf] final_amount_saved_registration: ' . $finalAmount );
        }

        // 2) CPT mirror
        $this->saveMeta( $registrationId, $payload, $userIds, $session, $vs, $finalAmount, (int) $dbRegistrationId );
        $dbRegistrationId = $this->ensureDbCptSync(
            (int) $registrationId,
            (int) $dbRegistrationId,
            (string) $vs,
            $payload,
            $session,
            $userIds,
            $finalAmount
        );
        $this->syncInvoiceUserMeta( $payload, $userIds );

        Logger::info( 'registration_created', [
            'registration_id' => $registrationId,
            'db_registration_id' => (int) $dbRegistrationId,
            'scope'           => $session->getScope(),
            'program_id'      => $programId,
            'client_user_id'  => $clientUserId,
        ] );

        return (int) $registrationId;
    }

    /**
     * Synchronizuje fakturačné údaje do user meta (profilové údaje).
     * Zapisuje len pri fakturácii na firmu; existujúce dáta inak nemení.
     */
    private function syncInvoiceUserMeta( RegistrationPayload $payload, array $userIds ): void {
        if ( ! $payload->invoiceToCompany ) {
            return;
        }

        $clientUserId = (int) ( $userIds['client_user_id'] ?? 0 );
        $parentUserId = isset( $userIds['parent_user_id'] ) ? (int) $userIds['parent_user_id'] : 0;
        $targetUserId = $parentUserId > 0 ? $parentUserId : $clientUserId;

        if ( $targetUserId <= 0 ) {
            return;
        }

        $companyName       = (string) ( $payload->companyName ?? '' );
        $companyIco        = (string) ( $payload->companyIco ?? '' );
        $companyDic        = (string) ( $payload->companyDic ?? '' );
        $companyIcdph      = (string) ( $payload->companyIcdph ?? '' );
        $addressStreet     = (string) ( $payload->companyAddressStreet ?? '' );
        $addressPostcode   = (string) ( $payload->companyAddressPostcode ?? '' );
        $addressCity       = (string) ( $payload->companyAddressCity ?? '' );
        $addressCountry    = (string) ( $payload->companyAddressCountry ?? '' );

        // Požadované company_* kľúče.
        update_user_meta( $targetUserId, 'company_name', $companyName );
        update_user_meta( $targetUserId, 'company_ico', $companyIco );
        update_user_meta( $targetUserId, 'company_dic', $companyDic );
        update_user_meta( $targetUserId, 'company_icdph', $companyIcdph );
        update_user_meta( $targetUserId, 'company_address_street', $addressStreet );
        update_user_meta( $targetUserId, 'company_address_zip', $addressPostcode );
        update_user_meta( $targetUserId, 'company_address_postcode', $addressPostcode );
        update_user_meta( $targetUserId, 'company_address_city', $addressCity );
        update_user_meta( $targetUserId, 'company_address_country', $addressCountry );

        // Existujúce profilové kľúče používané v SPA profile.
        update_user_meta( $targetUserId, 'billing_invoice_to_company', 1 );
        update_user_meta( $targetUserId, 'billing_company_name', $companyName );
        update_user_meta( $targetUserId, 'billing_company_ico', $companyIco );
        update_user_meta( $targetUserId, 'billing_company_dic', $companyDic );
        update_user_meta( $targetUserId, 'billing_company_icdph', $companyIcdph );
        update_user_meta( $targetUserId, 'billing_address_street', $addressStreet );
        update_user_meta( $targetUserId, 'billing_address_postcode', $addressPostcode );
        update_user_meta( $targetUserId, 'billing_address_city', $addressCity );
        update_user_meta( $targetUserId, 'billing_address_country', $addressCountry );
        update_user_meta( $targetUserId, 'billing_invoice_address_different', $payload->invoiceAddressDifferent ? 1 : 0 );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            spa_debug_log(
                '[SPA-INVOICE-SYNC] user_id=' . $targetUserId
                . ' company_name=' . $companyName
                . ' saved=1'
            );
        }
    }

    // ── Zápis postmeta ───────────────────────────────────────────────────────
    // Kľúče výhradne podľa SPA-DATA-MAP.md (sekcia spa_registration)

    private function saveMeta(
        int                $id,
        RegistrationPayload $payload,
        array              $userIds,
        SessionService     $session,
        string             $vs,
        float              $savedAmount,
        int                $dbRegistrationId = 0
    ): void {
        update_post_meta( $id, 'program_id',          $session->getProgramId() );
        update_post_meta( $id, 'client_user_id',      $userIds['client_user_id'] );
        update_post_meta( $id, 'parent_user_id',      $userIds['parent_user_id'] ?? '' );
        update_post_meta( $id, 'status',              'pending' );
        update_post_meta( $id, 'frequency_key',       $session->getFrequencyKey() );
        update_post_meta( $id, 'amount',              $savedAmount );
        update_post_meta( $id, 'external_surcharge',  $session->getExternalSurcharge() ?? '' );
        update_post_meta( $id, 'gf_entry_id',         $payload->gfEntryId );
        update_post_meta( $id, 'scope',               $session->getScope() );
        update_post_meta( $id, 'spa_vs', $vs );
        if ( $dbRegistrationId > 0 ) {
            update_post_meta( $id, 'db_registration_id', $dbRegistrationId );
        }
        update_post_meta( $id, 'payment_method',         (string) ( $payload->paymentMethod ?? '' ) );
        update_post_meta( $id, 'spa_invoice_tocompany',  $payload->invoiceToCompany ? '1' : '' );
        update_post_meta( $id, 'spa_invoice_address_different', $payload->invoiceAddressDifferent ? '1' : '' );
        update_post_meta( $id, 'company_name',           (string) ( $payload->companyName ?? '' ) );
        update_post_meta( $id, 'company_ico',            (string) ( $payload->companyIco ?? '' ) );
        update_post_meta( $id, 'company_dic',            (string) ( $payload->companyDic ?? '' ) );
        update_post_meta( $id, 'company_icdph',          (string) ( $payload->companyIcdph ?? '' ) );
        update_post_meta( $id, 'company_address_street', (string) ( $payload->companyAddressStreet ?? '' ) );
        update_post_meta( $id, 'company_address_city',   (string) ( $payload->companyAddressCity ?? '' ) );
        update_post_meta( $id, 'company_address_postcode', (string) ( $payload->companyAddressPostcode ?? '' ) );
        update_post_meta( $id, 'company_address_country', (string) ( $payload->companyAddressCountry ?? '' ) );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'SPA_DEBUG' ) && SPA_DEBUG ) {
            spa_debug_log( '[spa-phase-b-audit] cpt_meta_write: ' . wp_json_encode( [
                'registration_id' => $id,
                'meta_keys' => [
                    'payment_method',
                    'spa_invoice_tocompany',
                    'spa_invoice_address_different',
                    'company_name',
                    'company_ico',
                    'company_dic',
                    'company_icdph',
                    'company_address_street',
                    'company_address_city',
                    'company_address_postcode',
                    'company_address_country',
                    'db_registration_id',
                ],
            ] ) );
        }
    }

    /**
     * Garantuje DB ↔ CPT sync:
     * - DB row existuje
     * - CPT má meta db_registration_id
     */
    private function ensureDbCptSync(
        int $registrationId,
        int $dbRegistrationId,
        string $vs,
        RegistrationPayload $payload,
        SessionService $session,
        array $userIds,
        float $finalAmount
    ): int {
        $registrationId = (int) $registrationId;
        $dbRegistrationId = (int) $dbRegistrationId;
        $vs = (string) $vs;

        if ( $registrationId <= 0 ) {
            return 0;
        }

        if ( $dbRegistrationId <= 0 ) {
            $dbRegistrationId = $this->findDbRegistrationIdByVsAndUsers(
                $vs,
                (int) ( $userIds['client_user_id'] ?? 0 ),
                (int) $session->getProgramId(),
                isset( $userIds['parent_user_id'] ) ? (int) $userIds['parent_user_id'] : 0
            );
        }

        if ( $dbRegistrationId <= 0 ) {
            $dbRegistrationId = (int) $this->insertRegistrationToDb(
                (int) ( $userIds['client_user_id'] ?? 0 ),
                isset( $userIds['parent_user_id'] ) ? (int) $userIds['parent_user_id'] : 0,
                (int) $session->getProgramId(),
                'pending',
                (float) $finalAmount,
                (string) $session->getFrequencyKey(),
                (string) $vs,
                (string) ( $payload->paymentMethod ?? '' ),
                (int) $payload->invoiceToCompany,
                (int) $payload->invoiceAddressDifferent,
                (string) ( $payload->companyName ?? '' ),
                (string) ( $payload->companyIco ?? '' ),
                (string) ( $payload->companyDic ?? '' ),
                (string) ( $payload->companyIcdph ?? '' ),
                (string) ( $payload->companyAddressStreet ?? '' ),
                (string) ( $payload->companyAddressCity ?? '' ),
                (string) ( $payload->companyAddressPostcode ?? '' ),
                (string) ( $payload->companyAddressCountry ?? '' )
            );
        }

        if ( $dbRegistrationId > 0 ) {
            update_post_meta( $registrationId, 'db_registration_id', $dbRegistrationId );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                spa_debug_log(
                    'SPA-REGISTER-GF: DB↔CPT sync ok registration_id=' . $registrationId
                    . ' db_registration_id=' . $dbRegistrationId
                );
            }
        } else {
            Logger::error( 'registration_db_cpt_sync_failed', [
                'registration_id' => $registrationId,
                'gf_entry_id' => (int) $payload->gfEntryId,
                'program_id' => (int) $session->getProgramId(),
                'client_user_id' => (int) ( $userIds['client_user_id'] ?? 0 ),
            ] );
        }

        return (int) $dbRegistrationId;
    }

    /**
     * Jednoducha idempotencia pre retry GF submission:
     * vrat existujuce CPT ID pre rovnaky gf_entry_id.
     */
    private function findExistingRegistrationByEntryId( int $gfEntryId ): int {
        if ( $gfEntryId <= 0 ) {
            return 0;
        }

        $existing = get_posts( [
            'post_type'      => 'spa_registration',
            'post_status'    => [ 'publish', 'pending', 'draft', 'private', 'future' ],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'gf_entry_id',
                    'value'   => $gfEntryId,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ] );

        return ! empty( $existing ) ? (int) $existing[0] : 0;
    }

    /**
     * DB write pre novu registraciu.
     * Ak zlyha, vracia 0 a caller pokracuje fail-safe CPT flow.
     */
    private function insertRegistrationToDb(
        int $clientUserId,
        int $parentUserId,
        int $programId,
        string $status,
        float $amount,
        string $frequencyKey,
        string $vs,
        string $paymentMethod,
        int $invoiceToCompany,
        int $invoiceAddressDifferent,
        string $companyName,
        string $companyIco,
        string $companyDic,
        string $companyIcdph,
        string $companyAddressStreet,
        string $companyAddressCity,
        string $companyAddressPostcode,
        string $companyAddressCountry
    ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'spa_registrations';
        $createdAt = current_time( 'mysql' );

        $inserted = $wpdb->insert(
            $table,
            [
                'client_user_id' => $clientUserId,
                'parent_user_id' => $parentUserId,
                'program_id' => $programId,
                'status' => $status,
                'amount' => $amount,
                'frequency_key' => $frequencyKey,
                'spa_vs' => $vs,
                'payment_method' => $paymentMethod,
                'invoice_to_company' => $invoiceToCompany,
                'invoice_address_different' => $invoiceAddressDifferent,
                'company_name' => $companyName,
                'company_ico' => $companyIco,
                'company_dic' => $companyDic,
                'company_icdph' => $companyIcdph,
                'company_address_street' => $companyAddressStreet,
                'company_address_city' => $companyAddressCity,
                'company_address_postcode' => $companyAddressPostcode,
                'company_address_country' => $companyAddressCountry,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ],
            [ '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( $inserted === false ) {
            Logger::error( 'registration_db_insert_fail', [
                'table' => $table,
                'client_user_id' => $clientUserId,
                'program_id' => $programId,
                'last_error' => (string) $wpdb->last_error,
            ] );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                spa_debug_log(
                    'SPA-REGISTER-GF: DB INSERT fail table=' . $table
                    . ' error=' . (string) $wpdb->last_error
                );
            }
            return 0;
        }

        $dbRegistrationId = (int) $wpdb->insert_id;
        Logger::info( 'registration_db_insert_success', [
            'db_registration_id' => $dbRegistrationId,
            'table' => $table,
        ] );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if (defined('SPA_DEBUG') && SPA_DEBUG === true) {
                spa_debug_log( 'SPA-REGISTER-GF: DB INSERT success id=' . $dbRegistrationId );
            }
        }
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'SPA_DEBUG' ) && SPA_DEBUG ) {
            spa_debug_log( '[spa-phase-b-audit] db_write: ' . wp_json_encode( [
                'db_registration_id' => $dbRegistrationId,
                'db_columns' => [
                    'payment_method',
                    'invoice_to_company',
                    'invoice_address_different',
                    'company_name',
                    'company_ico',
                    'company_dic',
                    'company_icdph',
                    'company_address_street',
                    'company_address_city',
                    'company_address_postcode',
                    'company_address_country',
                ],
            ] ) );
        }

        return $dbRegistrationId;
    }

    private function findDbRegistrationIdByVsAndUsers(
        string $vs,
        int $clientUserId,
        int $programId,
        int $parentUserId = 0
    ): int {
        global $wpdb;
        $vs = (string) $vs;
        $clientUserId = (int) $clientUserId;
        $programId = (int) $programId;
        $parentUserId = (int) $parentUserId;

        if ( $vs === '' || $clientUserId <= 0 || $programId <= 0 ) {
            return 0;
        }

        $table = $wpdb->prefix . 'spa_registrations';
        $exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return 0;
        }

        if ( $parentUserId > 0 ) {
            $sql = $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE spa_vs = %s AND client_user_id = %d AND program_id = %d AND parent_user_id = %d
                 ORDER BY id DESC LIMIT 1",
                $vs,
                $clientUserId,
                $programId,
                $parentUserId
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE spa_vs = %s AND client_user_id = %d AND program_id = %d
                 ORDER BY id DESC LIMIT 1",
                $vs,
                $clientUserId,
                $programId
            );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Fallback generátor variabilného symbolu (3-miestny unikátny kód).
     * Unikátnosť voči wp_spa_registrations (spa_is_vs_unique), nie voči CPT meta.
     * Ak téma poskytuje spa_generate_vs(), tento sa nepoužije.
     */
    private function generateVs(): string {
        do {
            $vs = str_pad( (string) wp_rand( 100, 999 ), 3, '0', STR_PAD_LEFT );
            if ( \function_exists( '\spa_is_vs_unique' ) ) {
                $ok = \spa_is_vs_unique( $vs );
            } else {
                $ok = $this->generateVsLegacyCptCheck( $vs );
            }
        } while ( ! $ok );

        return $vs;
    }

    /**
     * @deprecated Používa sa len ak téma ešte nemá spa_is_vs_unique().
     */
    private function generateVsLegacyCptCheck( string $vs ): bool {
        $existing = get_posts( [
            'post_type'   => 'spa_registration',
            'meta_key'    => 'spa_vs',
            'meta_value'  => $vs,
            'numberposts' => 1,
            'fields'      => 'ids',
        ] );

        return empty( $existing );
    }
}