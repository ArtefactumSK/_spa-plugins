<?php
namespace SpaRegisterGf\Hooks;

use SpaRegisterGf\Infrastructure\GFFormFinder;
use SpaRegisterGf\Infrastructure\GFEntryReader;
use SpaRegisterGf\Services\SessionService;
use SpaRegisterGf\Services\FieldMapService;
use SpaRegisterGf\Services\AmountVerificationService;
use SpaRegisterGf\Validation\ChildScopeValidator;
use SpaRegisterGf\Validation\AdultScopeValidator;
use SpaRegisterGf\Infrastructure\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ValidationHooks {

    // ── gform_pre_validation ─────────────────────────────────────────────────

    /**
     * Kontrola session + expiry PRED GF validáciou.
     * Ak session chýba alebo expirovala → GF validation error.
     */
    public function handlePreValidation( array $form ): array {
        if ( ! GFFormFinder::guard( $form ) ) {
            return $form;
        }

        $session = SessionService::tryCreate();

        if ( ! $session ) {
            // Session chýba → nastav GF field chybu na prvé viditeľné pole
            add_filter( 'gform_validation', [ $this, 'forceSessionError' ] );
            Logger::warning( 'validation_session_missing' );
            return $form;
        }

        if ( $session->isExpired() ) {
            add_filter( 'gform_validation', [ $this, 'forceExpiredError' ] );
            Logger::warning( 'validation_session_expired' );
            return $form;
        }

        return $form;
    }

    // ── gform_validation ─────────────────────────────────────────────────────

    public function handleValidation( array $validationResult ): array {
        $form = $validationResult['form'] ?? [];

        if ( ! GFFormFinder::guard( $form ) ) {
            return $validationResult;
        }

        // Ak GF sám zistil chyby, nepokračujeme (vyhneme sa duplicitám)
        if ( ! $validationResult['is_valid'] ) {
            return $validationResult;
        }

        $session = SessionService::tryCreate();

        if ( ! $session || $session->isExpired() ) {
            return $this->blockWithMessage(
                $validationResult,
                'Platnosť výberu vypršala. Vráťte sa na výber programu a začnite odznova.'
            );
        }

        // Scope výhradne zo SESSION
        try {
            $scope = $session->getScope();
        } catch ( \RuntimeException $e ) {
            return $this->blockWithMessage(
                $validationResult,
                'Nastala chyba pri spracovaní. Skúste znovu.'
            );
        }

        // Scope-based validácia
        $entry   = $validationResult['form']['failed_validation_page'] ?? [];
        $allFields = $form['fields'] ?? [];
        $submittedValues = array_column( $allFields, null, 'id' );

        // Zostavíme RegistrationPayload z POST dát cez GFEntryReader
        // GF v tomto bode ešte nemá entry, takže čítame z $_POST cez rgar emuláciu
        $fakeEntry = $this->buildEntryFromPost( $form );
        $reader    = new GFEntryReader( $fakeEntry );
        $payload   = $reader->buildPayload();

        $validator = match ( $scope ) {
            'child' => new ChildScopeValidator(),
            'adult' => new AdultScopeValidator(),
            default => null,
        };

        if ( $validator ) {
            $result = $validator->validate( $payload );

            if ( ! $result->isValid() ) {
                foreach ( $result->getErrors() as $logicalKey => $message ) {
                    $validationResult = $this->addFieldError( $validationResult, $logicalKey, $message );
                }
                $validationResult['is_valid'] = false;
            }
        }

        // Blokujúca kontrola sumy
        if ( $validationResult['is_valid'] ) {
            $amountService = new AmountVerificationService();
            if ( ! $amountService->verify( $session ) ) {
                $validationResult = $this->blockWithMessage(
                    $validationResult,
                    'Cena programu sa zmenila. Vráťte sa na výber programu a pokračujte znovu.'
                );
            }
        }

        return $validationResult;
    }

    // ── Pomocné metódy ───────────────────────────────────────────────────────

    public function forceSessionError( array $validationResult ): array {
        return $this->blockWithMessage(
            $validationResult,
            'Výber programu chýba alebo vypršal. Vráťte sa na výber programu.'
        );
    }

    public function forceExpiredError( array $validationResult ): array {
        return $this->blockWithMessage(
            $validationResult,
            'Platnosť výberu vypršala (30 minút). Vráťte sa na výber programu a začnite odznova.'
        );
    }

    private function blockWithMessage( array $vr, string $message ): array {
        $vr['is_valid'] = false;

        // Pridaj globálnu správu na prvé pole formulára
        if ( ! empty( $vr['form']['fields'] ) ) {
            $vr['form']['fields'][0]->failed_validation  = true;
            $vr['form']['fields'][0]->validation_message = $message;
        }

        return $vr;
    }

    private function addFieldError( array $vr, string $logicalKey, string $message ): array {
        $fieldId = FieldMapService::tryResolve( $logicalKey );
        if ( $fieldId === null ) {
            return $vr;
        }

        // Nájdi GF field podľa id a nastav chybu
        foreach ( $vr['form']['fields'] as &$field ) {
            // GF field id môže byť "6.3" (subpole) – porovnávame s prefixom
            if ( (string) $field->id === explode( '.', $fieldId )[0] ) {
                $field->failed_validation  = true;
                $field->validation_message = $message;
                break;
            }
        }

        $vr['is_valid'] = false;
        return $vr;
    }

    /**
     * GF pri gform_validation ešte nemá $entry – simulujeme ho z $_POST.
     * rgar() funguje aj na POST polia priamo.
     */
    private function buildEntryFromPost( array $form ): array {
        $entry = [];
        foreach ( $form['fields'] ?? [] as $field ) {
            $fieldId = (string) $field->id;
            $entry[ $fieldId ] = rgpost( 'input_' . str_replace( '.', '_', $fieldId ) );
        }
        return $entry;
    }
}