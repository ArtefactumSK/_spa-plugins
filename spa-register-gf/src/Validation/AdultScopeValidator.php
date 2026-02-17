<?php
namespace SpaRegisterGf\Validation;

use SpaRegisterGf\Domain\RegistrationPayload;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdultScopeValidator extends AbstractScopeValidator {

    protected function doValidate( RegistrationPayload $p ): void {

        // Účastník – identifikácia
        $this->requireText(  'spa_member_name_first',      $p->memberFirstName,       'Meno účastníka' );
        $this->requireText(  'spa_member_name_last',       $p->memberLastName,        'Priezvisko účastníka' );
        $this->requireDate(  'spa_member_birthdate',       $p->memberBirthdate,       'Dátum narodenia' );

        // Kontakt – povinný e-mail pre adult
        $this->requireEmail( 'spa_client_email_required',  $p->clientEmailRequired,   'E-mail účastníka' );
        $this->requireText(  'spa_client_phone',           $p->clientPhone,           'Telefón účastníka' );

        // Adresa
        $this->requireText( 'spa_client_address_street',   $p->clientAddressStreet,   'Ulica a číslo' );
        $this->requireText( 'spa_client_address_city',     $p->clientAddressCity,     'Mesto' );
        $this->requireText( 'spa_client_address_postcode', $p->clientAddressPostcode, 'PSČ' );

        // Povinné súhlasy
        $this->requireConsent( 'spa_consent_gdpr',     $p->consentGdpr,     'GDPR súhlas' );
        $this->requireConsent( 'spa_consent_health',   $p->consentHealth,   'Súhlas so zdravotnými údajmi' );
        $this->requireConsent( 'spa_consent_statutes', $p->consentStatutes, 'Súhlas so stanovami' );
        $this->requireConsent( 'spa_consent_terms',    $p->consentTerms,    'Súhlas s podmienkami' );

        // spa_consent_marketing a spa_member_health_restrictions sú voliteľné
    }
}