<?php
namespace SpaRegisterGf\Services;

use SpaRegisterGf\Domain\RegistrationPayload;
use SpaRegisterGf\Infrastructure\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Rozvetvuje logiku vytvárania userov podľa scope zo SessionService.
 * Scope sa nikdy neodvodzuje z GF entry, POST ani GET.
 */
class UserCreationService {

    /**
     * @param  string $scope  výhradne zo SessionService::getScope()
     * @return array  [ 'client_user_id' => int ] alebo
     *                [ 'child_user_id' => int, 'parent_user_id' => int ]
     * @throws \RuntimeException pri zlyhaní
     */
    public function createForScope( RegistrationPayload $payload, string $scope ): array {
        return match ( $scope ) {
            'child' => ( new UserCreationChildHelper() )->create( $payload ),
            'adult' => ( new UserCreationAdultHelper() )->create( $payload ),
            default => throw new \RuntimeException( "Neznámy scope: $scope" ),
        };
    }
}