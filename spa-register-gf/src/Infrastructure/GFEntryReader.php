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
     * Pre product fields používa GFCommon::get_product_fields.
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

        // ── Špeciálna logika pre product fields ────────────────────────────────
        if ( $logicalKey === 'spa_first_payment_amount' ) {
            return $this->getProductFieldValue( $fieldId );
        }

        // ── Štandardné čítanie pre ostatné polia ───────────────────────────────
        $gfKey = preg_replace( '/^input_/', '', $fieldId );
        $gfKey = str_replace( '_', '.', $gfKey );
        $val   = rgar( $this->entry, $gfKey );
        return $val !== null && $val !== '' ? (string) $val : null;
    }

    /**
     * Public wrapper pre interné getEntryValue – vráti raw hodnotu z entry.
     * Použité napr. pre server-side verifikáciu sumy.
     */
    public function get( string $logicalKey ): ?string {
        return $this->getEntryValue( $logicalKey );
    }

    /**
     * Získa hodnotu produktového poľa cez GFCommon::get_product_fields.
     * Fallback na GF total pole ak produkt nenájde.
     */
    private function getProductFieldValue( string $fieldId ): ?string {
        // Extrahuj field ID (napr. "input_63" -> 63)
        $numericId = (int) preg_replace( '/^input_/', '', $fieldId );
        if ( $numericId <= 0 ) {
            return null;
        }

        // Získaj form z entry
        $formId = (int) rgar( $this->entry, 'form_id', 0 );
        if ( $formId <= 0 ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                if (defined('SPA_DEBUG') && SPA_DEBUG) {
                spa_debug_log( '[spa-register-gf] GFEntryReader: Cannot get form_id from entry for product field ' . $numericId );
                }
            }
            return $this->fallbackToTotal();
        }

        $form = \GFAPI::get_form( $formId );
        if ( ! $form || is_wp_error( $form ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                if (defined('SPA_DEBUG') && SPA_DEBUG) {
                spa_debug_log( '[spa-register-gf] GFEntryReader: Cannot get form ' . $formId . ' for product field ' . $numericId );
                }
            }
            return $this->fallbackToTotal();
        }

        // Získaj produkty cez GFCommon::get_product_fields
        $products = \GFCommon::get_product_fields( $form, $this->entry );
        if ( empty( $products ) || ! is_array( $products ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                if (defined('SPA_DEBUG') && SPA_DEBUG) {
                spa_debug_log( '[spa-register-gf] GFEntryReader: No products found for field ' . $numericId );
                }
            }
            return $this->fallbackToTotal();
        }

        // Nájdi produkt podľa field ID
        foreach ( $products as $product ) {
            if ( isset( $product['id'] ) && (int) $product['id'] === $numericId ) {
                // Získaj cenu produktu
                $price = isset( $product['price'] ) ? $product['price'] : null;
                if ( $price !== null && $price !== '' ) {
                    return (string) $price;
                }
            }
        }

        // Fallback na total pole
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if (defined('SPA_DEBUG') && SPA_DEBUG) {
            spa_debug_log( '[spa-register-gf] GFEntryReader: Product field ' . $numericId . ' not found in products, falling back to total' );
            }
        }
        return $this->fallbackToTotal();
    }

    /**
     * Fallback: získa hodnotu z GF total poľa.
     */
    private function fallbackToTotal(): ?string {
        $totalFieldId = FieldMapService::tryResolve( 'spa_first_payment_total' );
        if ( $totalFieldId === null ) {
            $totalFieldId = FieldMapService::tryResolve( 'spa_first_payment_total' );
        }
        if ( $totalFieldId === null ) {
            return null;
        }

        $gfKey = preg_replace( '/^input_/', '', $totalFieldId );
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
        $p->clientAddressCountry   = $this->tryGetText( 'spa_client_address_country' );

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

        // Platba + fakturácia
        $p->paymentMethod          = $this->tryGetText( 'payment_method' );
        $p->invoiceToCompany       = $this->getBool( 'spa_invoice_tocompany' );
        $p->invoiceAddressDifferent = $this->getBool( 'spa_invoice_address_different' );
        $p->companyName            = $this->tryGetText( 'company_name' );
        $p->companyIco             = $this->tryGetText( 'company_ico' );
        $p->companyDic             = $this->tryGetText( 'company_dic' );
        $p->companyIcdph           = $this->tryGetText( 'company_icdph' );
        $p->companyAddressStreet   = $this->tryGetText( 'company_address_street' );
        $p->companyAddressCity     = $this->tryGetText( 'company_address_city' );
        $p->companyAddressPostcode = $this->tryGetText( 'company_address_postcode' );
        $p->companyAddressCountry  = $this->tryGetText( 'company_address_country' );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'SPA_DEBUG' ) && SPA_DEBUG ) {
            $auditKeys = [
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
            ];
            $resolved = [];
            foreach ( $auditKeys as $key ) {
                $resolved[ $key ] = FieldMapService::tryResolve( $key ) !== null;
            }
            spa_debug_log( '[spa-phase-b-audit] payload_build: ' . wp_json_encode( [
                'entry_id' => $p->gfEntryId,
                'resolved_keys' => $resolved,
                'filled' => [
                    'payment_method' => $p->paymentMethod,
                    'invoice_to_company' => $p->invoiceToCompany,
                    'invoice_address_different' => $p->invoiceAddressDifferent,
                    'company_name' => $p->companyName,
                    'company_ico' => $p->companyIco,
                    'company_dic' => $p->companyDic,
                    'company_icdph' => $p->companyIcdph,
                    'company_address_street' => $p->companyAddressStreet,
                    'company_address_city' => $p->companyAddressCity,
                    'company_address_postcode' => $p->companyAddressPostcode,
                    'company_address_country' => $p->companyAddressCountry,
                ],
            ] ) );
        }

        return $p;
    }
}