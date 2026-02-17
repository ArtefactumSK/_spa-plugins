<?php
namespace SpaRegisterGf\Validation;

use SpaRegisterGf\Domain\RegistrationPayload;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class AbstractScopeValidator {

    protected ValidationResult $result;

    public function validate( RegistrationPayload $payload ): ValidationResult {
        $this->result = new ValidationResult();
        $this->doValidate( $payload );
        return $this->result;
    }

    abstract protected function doValidate( RegistrationPayload $payload ): void;

    // ── Zdieľané pomocné metódy ──────────────────────────────────────────────

    protected function requireText( string $logicalKey, ?string $value, string $label ): void {
        if ( empty( trim( $value ?? '' ) ) ) {
            $this->result->addError( $logicalKey, "$label je povinné pole." );
        }
    }

    protected function requireEmail( string $logicalKey, ?string $value, string $label ): void {
        if ( empty( trim( $value ?? '' ) ) ) {
            $this->result->addError( $logicalKey, "$label je povinné pole." );
            return;
        }
        if ( ! is_email( $value ) ) {
            $this->result->addError( $logicalKey, "$label musí byť platná e-mailová adresa." );
        }
    }

    protected function requireDate( string $logicalKey, ?string $value, string $label ): void {
        if ( empty( trim( $value ?? '' ) ) ) {
            $this->result->addError( $logicalKey, "$label je povinné pole." );
            return;
        }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            $this->result->addError( $logicalKey, "$label musí byť vo formáte RRRR-MM-DD." );
        }
    }

    protected function requireConsent( string $logicalKey, bool $value, string $label ): void {
        if ( ! $value ) {
            $this->result->addError( $logicalKey, "$label je povinný súhlas." );
        }
    }
}