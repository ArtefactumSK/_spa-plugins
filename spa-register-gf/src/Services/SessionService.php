<?php
namespace SpaRegisterGf\Services;

use SpaRegisterGf\Infrastructure\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only prístup k $_SESSION['spa_registration'].
 * Plugin NESMIE nikdy meniť SESSION.
 */
class SessionService {

    private array $data;

    // ── Konštruktor ──────────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException ak session chýba
     */
    public function __construct() {
        $raw = $_SESSION[ SPA_REG_GF_SESSION_KEY ] ?? null;

        if ( ! is_array( $raw ) || empty( $raw ) ) {
            throw new \RuntimeException( 'session_missing' );
        }

        $this->data = $raw;
    }

    // ── Statický factory ─────────────────────────────────────────────────────

    /**
     * Vracia null ak session neexistuje (bezpečné volanie bez výnimky).
     */
    public static function tryCreate(): ?self {
        try {
            return new self();
        } catch ( \RuntimeException $e ) {
            return null;
        }
    }

    // ── Gettery ──────────────────────────────────────────────────────────────

    public function getProgramId(): int {
        return (int) ( $this->data['program_id'] ?? 0 );
    }

    public function getFrequencyKey(): string {
        return (string) ( $this->data['frequency_key'] ?? '' );
    }

    public function getAmount(): float {
        return (float) ( $this->data['amount'] ?? 0.0 );
    }

    public function getExternalSurcharge(): ?string {
        $val = $this->data['external_surcharge'] ?? null;
        return ( $val !== null && $val !== '' ) ? (string) $val : null;
    }

    /**
     * Scope je autoritatívny VÝHRADNE zo SESSION.
     * Platné hodnoty: 'child' | 'adult'
     *
     * @throws \RuntimeException ak scope chýba alebo je neplatný
     */
    public function getScope(): string {
        $scope = $this->data['scope'] ?? null;

        if ( ! in_array( $scope, [ 'child', 'adult' ], true ) ) {
            Logger::error( 'session_scope_invalid', [ 'scope' => $scope ] );
            throw new \RuntimeException( 'session_scope_invalid' );
        }

        return $scope;
    }

    public function getCreatedAt(): string {
        return (string) ( $this->data['created_at'] ?? '' );
    }

    // ── Expiry check ─────────────────────────────────────────────────────────

    /**
     * Kontrola expirácie: 30 minút od created_at.
     * Blokujúca – volá sa v PRE_VALIDATION aj AFTER_SUBMISSION.
     */
    public function isExpired(): bool {
        $createdAt = $this->getCreatedAt();

        if ( empty( $createdAt ) ) {
            return true; // Ak nie je timestamp, považujeme za expired
        }

        try {
            $created = new \DateTime( $createdAt );
            $now     = new \DateTime();
            $diff    = $now->getTimestamp() - $created->getTimestamp();
            return $diff > SPA_REG_GF_SESSION_TTL;
        } catch ( \Exception $e ) {
            return true;
        }
    }

    // ── Validácia kompletnosti ────────────────────────────────────────────────

    /**
     * Overí, že všetky povinné kľúče existujú a nie sú prázdne.
     * Vracia pole chýbajúcich kľúčov (prázdne pole = OK).
     */
    public function getMissingKeys(): array {
        $required = [ 'program_id', 'frequency_key', 'amount', 'scope', 'created_at' ];
        $missing  = [];

        foreach ( $required as $key ) {
            if ( empty( $this->data[ $key ] ) && $this->data[ $key ] !== 0 ) {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}