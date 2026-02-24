<?php
namespace SpaRegisterGf\Infrastructure;

use SpaRegisterGf\Services\FieldMapService;
use SpaRegisterGf\Domain\RegistrationPayload;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Číta GF entry výhradne cez logical keys + FieldMapService.
 * Nikdy nepoužíva GF field identifikátory priamo.
 * Vykonáva sanitizáciu na vstupe.
 */
class GFEntryReader {

    private array $entry;

    public function __construct( array $entry ) {
        $this->entry = $entry;
    }

    /**
     * Vráti hodnotu z entry – preferuje logical key (buildEntryFromPost),
     * inak fallback na field ID (reálny GF entry).
     */
    private function getEntryValue( string $logicalKey ): ?string {
        $val = rgar( $this->entry, $logicalKey );
        if ( $val !== null && $val !== '' ) {
            return (string) $val;
        }
        $fieldId = FieldMapService::tryResolve( $logicalKey );
        if ( $fieldId === null ) {
            return null;
        }
        $gfKey = preg_replace( '/^input_/', '', $fieldId );
        $gfKey = str_replace( '_', '.', $gfKey );
        $val   = rgar( $this->entry, $gfKey );
        return $val !== null && $val !== '' ? (string) $val : null;
    }

    // ── Sanitizované čítanie ─────────────────────────────────────────────────

    public function getText( string $logicalKey ): string {
        return sanitize_text_field( (string) ( $this->getEntryValue( $logicalKey ) ?? '' ) );
    }

    public function getEmail( string $logicalKey ): string {
        return sanitize_email( (string) ( $this->getEntryValue( $logicalKey ) ?? '' ) );
    }

    public function getTextarea( string $logicalKey ): string {
        return sanitize_textarea_field( (string) ( $this->getEntryValue( $logicalKey ) ?? '' ) );
    }

    public function getBool( string $logicalKey ): bool {
        return ! empty( $this->getEntryValue( $logicalKey ) );
    }

    // ── Bezpečné čítanie (null ak key neexistuje v mape) ─────────────────────

    public function tryGetText( string $logicalKey ): ?string {
        $val = $this->getEntryValue( $logicalKey );
        return $val !== null ? sanitize_text_field( $val ) : null;
    }

    // ── GF entry ID ──────────────────────────────────────────────────────────

    public function getEntryId(): int {
        return (int) ( $this->entry['id'] ?? 0 );
    }

    // ── Zostavenie DTO ───────────────────────────────────────────────────────

    /**
     * Zostaví RegistrationPayload z entry.
     * Všetky polia sú pomenované logical keys.
     */
    public function buildPayload(): RegistrationPayload {
        $p = new RegistrationPayload();

        $p->gfEntryId = $this->getEntryId();

        // Účastník / člen
        $p->memberFirstName        = $this->getText( 'spa_member_name_first' );
        $p->memberLastName         = $this->getText( 'spa_member_name_last' );
        $p->memberBirthdate        = $this->getText( 'spa_member_birthdate' );
        $p->memberBirthnumber      = $this->tryGetText( 'spa_member_birthnumber' );
        $p->memberHealthRestrictions = $this->tryGetText( 'spa_member_health_restrictions' );

        // Kontakt účastníka (adult)
        $p->clientEmail            = $this->tryGetText( 'spa_client_email' );
        $p->clientEmailRequired    = $this->tryGetText( 'spa_client_email_required' );
        $p->clientPhone            = $this->tryGetText( 'spa_client_phone' );
        $p->clientAddressStreet    = $this->tryGetText( 'spa_client_address_street' );
        $p->clientAddressCity      = $this->tryGetText( 'spa_client_address_city' );
        $p->clientAddressPostcode  = $this->tryGetText( 'spa_client_address_postcode' );

        // Rodič / guardian (child scope)
        $p->guardianFirstName      = $this->tryGetText( 'spa_guardian_name_first' );
        $p->guardianLastName       = $this->tryGetText( 'spa_guardian_name_last' );
        $p->parentEmail            = $this->tryGetText( 'spa_parent_email' );
        $p->parentPhone            = $this->tryGetText( 'spa_parent_phone' );

        // Súhlasy
        $p->consentGdpr            = $this->getBool( 'spa_consent_gdpr' );
        $p->consentHealth          = $this->getBool( 'spa_consent_health' );
        $p->consentStatutes        = $this->getBool( 'spa_consent_statutes' );
        $p->consentTerms           = $this->getBool( 'spa_consent_terms' );
        $p->consentGuardian        = $this->getBool( 'spa_consent_guardian' );
        $p->consentMarketing       = $this->getBool( 'spa_consent_marketing' );

        return $p;
    }
}