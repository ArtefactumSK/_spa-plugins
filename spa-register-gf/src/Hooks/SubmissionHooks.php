<?php
namespace SpaRegisterGf\Hooks;

use SpaRegisterGf\Infrastructure\GFFormFinder;
use SpaRegisterGf\Infrastructure\GFEntryReader;
use SpaRegisterGf\Infrastructure\Logger;
use SpaRegisterGf\Services\SessionService;
use SpaRegisterGf\Services\AmountVerificationService;
use SpaRegisterGf\Services\UserCreationService;
use SpaRegisterGf\Services\RegistrationService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SubmissionHooks {

    public function handle( array $entry, array $form ): void {
        if ( ! GFFormFinder::guard( $form ) ) {
            return;
        }

        // ── Krok 1: Session guard + expiry ───────────────────────────────────
        $session = SessionService::tryCreate();

        if ( ! $session ) {
            Logger::error( 'submission_session_missing', [ 'entry_id' => $entry['id'] ?? 0 ] );
            $this->redirectToSelector();
            return;
        }

        if ( $session->isExpired() ) {
            Logger::warning( 'submission_session_expired', [ 'entry_id' => $entry['id'] ?? 0 ] );
            $this->redirectToSelector();
            return;
        }

        // ── Krok 2: Scope výhradne zo SESSION ────────────────────────────────
        try {
            $scope = $session->getScope();
        } catch ( \RuntimeException $e ) {
            Logger::error( 'submission_scope_invalid', [ 'entry_id' => $entry['id'] ?? 0 ] );
            return;
        }

        // ── Krok 3: Amount verification (blokujúci) – cez session + GF entry ─
        $amountService = new AmountVerificationService();
        if ( ! $amountService->verify( $session, $entry ) ) {
            Logger::error( 'submission_amount_mismatch', [
                'session_amount' => $session->getAmount(),
                'program_id'     => $session->getProgramId(),
                'entry_id'       => $entry['id'] ?? 0,
            ] );
            // GF entry je už uložený – zakážeme ďalšie spracovanie, admin upozorníme
            do_action( 'spa_registration_amount_mismatch', $entry, $session );
            return;
        }

        // ── Krok 4: Zostavenie payload ────────────────────────────────────────
        $reader  = new GFEntryReader( $entry );
        $payload = $reader->buildPayload();

        // ── Krok 5: Vytvorenie userov ─────────────────────────────────────────
        try {
            $userCreation = new UserCreationService();
            $userIds      = $userCreation->createForScope( $payload, $scope );
        } catch ( \RuntimeException $e ) {
            Logger::error( 'submission_user_creation_failed', [
                'scope'    => $scope,
                'message'  => $e->getMessage(),
                'entry_id' => $entry['id'] ?? 0,
            ] );
            do_action( 'spa_registration_failed', $entry, $e->getMessage() );
            return;
        }

        // ── Krok 6: Vytvorenie registrácie ────────────────────────────────────
        try {
            $regService     = new RegistrationService();
            $registrationId = $regService->create( $payload, $userIds, $session );
        } catch ( \RuntimeException $e ) {
            Logger::error( 'submission_registration_failed', [
                'scope'    => $scope,
                'message'  => $e->getMessage(),
                'entry_id' => $entry['id'] ?? 0,
            ] );
            do_action( 'spa_registration_failed', $entry, $e->getMessage() );
            return;
        }

        // ── Krok 7: Post-registration akcie ──────────────────────────────────
        do_action( 'spa_registration_completed', $registrationId, $userIds, $session );

        Logger::info( 'submission_complete', [
            'registration_id' => $registrationId,
            'scope'           => $scope,
            'program_id'      => $session->getProgramId(),
        ] );
    }

    private function redirectToSelector(): void {
        if ( ! headers_sent() ) {
            wp_safe_redirect( home_url( SPA_REG_GF_SELECTOR_URL ) );
            exit;
        }
    }
}