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
        error_log('---- SPA VALIDATION START ----');
        error_log('SESSION STATUS: ' . session_status());
        error_log('SESSION DATA: ' . print_r($_SESSION['spa_registration'] ?? null, true));
        error_log('POST input_55: ' . ($_POST['input_55'] ?? 'NULL'));
        $form = $validationResult['form'] ?? [];

        error_log( '[spa-register-gf] handleValidation called | is_valid=' . ( $validationResult['is_valid'] ? 'true' : 'false' ) . ' | cssClass="' . ( $form['cssClass'] ?? '' ) . '"' );

        if ( ! GFFormFinder::guard( $form ) ) {
            error_log( '[spa-register-gf] handleValidation guard=false → skip' );
            error_log('VALIDATION FAILED AT: <NAME_OF_GUARD>');
            return $validationResult;
        }

        $entry = $this->buildEntryFromPost( $form );

        // === AUTO REMOVE REQUIRED FOR HIDDEN FIELDS ===
        foreach ( $form['fields'] as &$field ) {

            if ( $field->isRequired ) {

                $isHidden = \GFFormsModel::is_field_hidden(
                    $form,
                    $field,
                    $entry
                );

                if ( $isHidden ) {
                    $field->isRequired = false;
                }
            }
        }

        // Ak GF sám zistil chyby, nepokračujeme (vyhneme sa duplicitám)
        if ( ! $validationResult['is_valid'] ) {
            error_log('VALIDATION FAILED: ' . print_r($validationResult, true));
            return $validationResult;
        }

        $session = SessionService::tryCreate();

        if ( ! $session ) {
            Logger::warning( 'validation_block_session_null' );
            return $this->blockWithMessage(
                $validationResult,
                'Platnosť výberu vypršala. Vráťte sa na výber programu a začnite odznova.'
            );
        }

        if ( $session->isExpired() ) {
            Logger::warning( 'validation_block_session_expired', [
                'created_at' => $session->getCreatedAt(),
                'now'        => time(),
            ] );
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
                error_log( '[spa-register-gf] ScopeValidator FAILED | errors=' . json_encode( $result->getErrors() ) );
                foreach ( $result->getErrors() as $logicalKey => $message ) {
                    $validationResult = $this->addFieldError( $validationResult, $logicalKey, $message );
                }
                $validationResult['is_valid'] = false;
            } else {
                error_log( '[spa-register-gf] ScopeValidator OK' );
            }
        }

        // Blokujúca kontrola sumy
        if ( $validationResult['is_valid'] ) {
            $amountService = new AmountVerificationService();
            $amountOk = $amountService->verify( $session );
            error_log( '[spa-register-gf] AmountVerification result=' . ( $amountOk ? 'true' : 'false' ) );
            if ( ! $amountOk ) {
                $validationResult = $this->blockWithMessage(
                    $validationResult,
                    'Cena programu sa zmenila. Vráťte sa na výber programu a pokračujte znovu.'
                );
            }
        }
        error_log( '[spa-register-gf] handleValidation END | is_valid=' . ( $validationResult['is_valid'] ? 'true' : 'false' ) );
        error_log('FINAL VALIDATION RESULT: ' . print_r($validationResult, true));
        return $validationResult;
    }

    // ── Pomocné metódy ───────────────────────────────────────────────────────

    public function forceSessionError( array $validationResult ): array {
        error_log('FORCE SESSION ERROR CALLED');
        return $this->blockWithMessage(
            $validationResult,
            'Výber programu chýba alebo vypršal. Vráťte sa na výber programu.'
        );
    }

    public function forceExpiredError( array $validationResult ): array {
        error_log('FORCE EXPIRED ERROR CALLED');
        return $this->blockWithMessage(
            $validationResult,
            'Platnosť výberu vypršala (30 minút). Vráťte sa na výber programu a začnite odznova.'
        );
    }

    private function blockWithMessage( array $vr, string $message ): array {
        error_log('BLOCK WITH MESSAGE CALLED: ' . $message);
        $vr['is_valid'] = false;

        // Pridaj globálnu správu na prvé pole formulára
        if ( ! empty( $vr['form']['fields'] ) ) {
            $vr['form']['fields'][0]->failed_validation  = true;
            $vr['form']['fields'][0]->validation_message = $message;
        }

        return $vr;
    }

    private function addFieldError( array $vr, string $logicalKey, string $message ): array {
        error_log('ADD FIELD ERROR CALLED: ' . $logicalKey . ' - ' . $message);
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
     * Vracia entry s kľúčmi podľa adminLabel (logical keys) pre ScopeValidator.
     * Používa \rgpost().
     *
     * Mapovanie:
     * - Name (spa_member_name): 6.3→first, 6.6→last
     * - Name (spa_guardian_name): 18.3→first, 18.6→last
     * - Address: adminLabel + _street, _city, _postcode
     * - Checkbox: enableChoiceValue=true → hodnoty podľa choice value
     * - Jednoduché: adminLabel ako key (bez adminLabel preskoč)
     */
    private function buildEntryFromPost( array $form ): array {
        error_log( '[spa-register-gf] buildEntryFromPost called' );
        $entry = [];

        foreach ( $form['fields'] ?? [] as $field ) {
            $fieldId    = (string) $field->id;
            $adminLabel = isset( $field->adminLabel ) ? trim( (string) $field->adminLabel ) : '';
            $type       = $field->type ?? '';

            // ── Name field ───────────────────────────────────────────────────
            // adminLabel spa_member_name → 6.3=first, 6.6=last
            // adminLabel spa_guardian_name → 18.3=first, 18.6=last
            if ( $type === 'name' && $adminLabel !== '' ) {
                $nameInputMap = [
                    '3' => $adminLabel . '_first',
                    '6' => $adminLabel . '_last',
                ];
                foreach ( $nameInputMap as $suffix => $logicalKey ) {
                    $postKey = 'input_' . str_replace( '.', '_', $fieldId . '.' . $suffix );
                    $val     = \rgpost( $postKey );
                    if ( $val !== null && $val !== '' ) {
                        $entry[ $logicalKey ] = $val;
                    }
                }
                continue;
            }

            // ── Address field ─────────────────────────────────────────────────
            // adminLabel + suffix: _street, _city, _postcode
            if ( $type === 'address' && $adminLabel !== '' ) {
                $addrInputMap = [
                    '1' => $adminLabel . '_street',
                    '3' => $adminLabel . '_city',
                    '5' => $adminLabel . '_postcode',
                ];
                foreach ( $addrInputMap as $suffix => $logicalKey ) {
                    $postKey = 'input_' . str_replace( '.', '_', $fieldId . '.' . $suffix );
                    $val     = \rgpost( $postKey );
                    if ( $val !== null && $val !== '' ) {
                        $entry[ $logicalKey ] = $val;
                    }
                }
                continue;
            }

            // ── Checkbox field ────────────────────────────────────────────────
            if ( $type === 'checkbox' ) {
                // enableChoiceValue=true → entry[choice.value]=true (bez adminLabel)
                if ( ! empty( $field->enableChoiceValue ) ) {
                    $inputs  = (array) ( $field->inputs ?? [] );
                    $choices = (array) ( $field->choices ?? [] );
                    foreach ( $inputs as $idx => $input ) {
                        $postKey = 'input_' . str_replace( '.', '_', (string) $input['id'] );
                        $value   = \rgpost( $postKey );
                        if ( $value !== null && $value !== '' ) {
                            $choice     = $choices[ $idx ] ?? null;
                            if ( $choice !== null ) {
                                $logicalKey   = $choice['value'];
                                $entry[ $logicalKey ] = true;
                            }
                        }
                    }
                    continue;
                }
                // enableChoiceValue=false → pôvodná logika s adminLabel
                if ( $adminLabel === '' ) {
                    continue;
                }
                $inputs       = (array) ( $field->inputs ?? [] );
                $choices      = (array) ( $field->choices ?? [] );
                $singleInput  = count( $inputs ) <= 1;
                foreach ( $inputs as $idx => $input ) {
                    $inputId   = (string) $input['id'];
                    $postKey   = 'input_' . str_replace( '.', '_', $inputId );
                    $val       = \rgpost( $postKey );
                    if ( $val === null || $val === '' ) {
                        continue;
                    }
                    $logicalKey = $singleInput ? $adminLabel : ( $adminLabel . '_' . ( explode( '.', $inputId )[1] ?? ( $idx + 1 ) ) );
                    $entry[ $logicalKey ] = $val;
                }
                continue;
            }

            // ── Jednoduché polia (text, email, date, …) ───────────────────────
            // Ak majú adminLabel, použi ho; inak preskoč
            if ( $adminLabel === '' ) {
                continue;
            }

            if ( ! empty( $field->inputs ) && is_array( $field->inputs ) ) {
                foreach ( $field->inputs as $input ) {
                    $inputId = (string) $input['id'];
                    $postKey = 'input_' . str_replace( '.', '_', $inputId );
                    $val     = \rgpost( $postKey );
                    if ( $val !== null && $val !== '' ) {
                        $entry[ $adminLabel ] = $val;
                        break;
                    }
                }
            } else {
                $postKey = 'input_' . str_replace( '.', '_', $fieldId );
                $val     = \rgpost( $postKey );
                if ( $val !== null && $val !== '' ) {
                    $entry[ $adminLabel ] = $val;
                }
            }
        }

        error_log( '[spa-register-gf] buildEntryFromPost keys: ' . implode( ', ', array_keys( $entry ) ) );
        return $entry;
    }
}
