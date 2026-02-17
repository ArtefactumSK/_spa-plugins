<?php
namespace SpaRegisterGf\Validation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ValidationResult {

    private bool  $valid  = true;
    private array $errors = [];   // [ 'logical_key' => 'správa chyby' ]

    public function addError( string $logicalKey, string $message ): void {
        $this->valid            = false;
        $this->errors[ $logicalKey ] = $message;
    }

    public function isValid(): bool {
        return $this->valid;
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function hasError( string $logicalKey ): bool {
        return isset( $this->errors[ $logicalKey ] );
    }
}