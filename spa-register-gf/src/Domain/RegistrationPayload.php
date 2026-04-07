<?php
namespace SpaRegisterGf\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DTO – nesie všetky extrahované dáta z GF entry.
 * Všetky vlastnosti sú pomenované podľa logical keys.
 * Žiadny GF field identifikátor sa tu nenachádza.
 */
class RegistrationPayload {

    public int     $gfEntryId             = 0;

    // Účastník
    public string  $memberFirstName       = '';
    public string  $memberLastName        = '';
    public string  $memberBirthdate       = '';
    public ?string $memberBirthnumber     = null;
    public ?string $memberHealthRestrictions = null;

    // Kontakt (adult)
    public ?string $clientEmail           = null;
    public ?string $clientEmailRequired   = null;
    public ?string $clientPhone           = null;
    public ?string $clientAddressStreet   = null;
    public ?string $clientAddressCity     = null;
    public ?string $clientAddressPostcode = null;
    public ?string $clientAddressCountry  = null;

    // Rodič (child)
    public ?string $guardianFirstName     = null;
    public ?string $guardianLastName      = null;
    public ?string $parentEmail           = null;
    public ?string $parentPhone           = null;

    // Súhlasy
    public bool    $consentGdpr           = false;
    public bool    $consentHealth         = false;
    public bool    $consentStatutes       = false;
    public bool    $consentTerms          = false;
    public bool    $consentGuardian       = false;
    public bool    $consentMarketing      = false;

    /**
     * Spôsob platby z GF (logical key "payment_method").
     * Očakávané hodnoty podľa GF konfigurácie:
     * - cash_payment
     * - invoice_payment
     * - online_payment (Stripe Checkout)
     */
    public ?string $paymentMethod         = null;

    // Fakturácia (invoice/company)
    public bool    $invoiceToCompany      = false;
    public bool    $invoiceAddressDifferent = false;
    public ?string $companyName           = null;
    public ?string $companyIco            = null;
    public ?string $companyDic            = null;
    public ?string $companyIcdph          = null;
    public ?string $companyAddressStreet  = null;
    public ?string $companyAddressCity    = null;
    public ?string $companyAddressPostcode = null;
    public ?string $companyAddressCountry = null;
}