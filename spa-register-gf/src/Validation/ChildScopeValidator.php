<?php
namespace SpaRegisterGf\Validation;

use SpaRegisterGf\Domain\RegistrationPayload;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ChildScopeValidator extends AbstractScopeValidator {

    protected function doValidate( RegistrationPayload $p ): void {

        // Účastník – identifikácia
        $this->requireText(  'spa_member_name_first', $p->memberFirstName,   'Meno účastníka' );
        $this->requireText(  'spa_member_name_last',  $p->memberLastName,    'Priezvisko účastníka' );
        $this->requireDate(  'spa_member_birthdate',  $p->memberBirthdate,   'Dátum narodenia' );

        // Rodič / zákonný zástupca
        $this->requireText(  'spa_guardian_name_first', $p->guardianFirstName, 'Meno zákonného zástupcu' );
        $this->requireText(  'spa_guardian_name_last',  $p->guardianLastName,  'Priezvisko zákonného zástupcu' );
        $this->requireEmail( 'spa_parent_email',        $p->parentEmail,       'E-mail zákonného zástupcu' );
        $this->requireText(  'spa_parent_phone',        $p->parentPhone,       'Telefón zákonného zástupcu' );

        // Povinné súhlasy
        $this->requireConsent( 'spa_consent_gdpr',     $p->consentGdpr,     'GDPR súhlas' );
        $this->requireConsent( 'spa_consent_health',   $p->consentHealth,   'Súhlas so zdravotnými údajmi' );
        $this->requireConsent( 'spa_consent_statutes', $p->consentStatutes, 'Súhlas so stanovami' );
        $this->requireConsent( 'spa_consent_terms',    $p->consentTerms,    'Súhlas s podmienkami' );
        $this->requireConsent( 'spa_consent_guardian', $p->consentGuardian, 'Potvrdenie zákonného zástupcu' );

        // spa_member_birthnumber a spa_member_health_restrictions sú voliteľné
    }
}