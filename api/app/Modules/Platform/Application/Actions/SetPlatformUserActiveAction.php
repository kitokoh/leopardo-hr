<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use App\Modules\Platform\Infrastructure\Services\PlatformUserDirectoryService;

/**
 * Activation/désactivation d'un utilisateur plateforme avec garde
 * d'auto-désactivation — cas d'usage extrait de PlatformUsersController
 * (issue #6569, audit DDD M1). Délègue à PlatformUserDirectoryService
 * (Infrastructure).
 */
final class SetPlatformUserActiveAction
{
    public function __construct(
        private readonly PlatformUserDirectoryService $directory,
    ) {}

    /**
     * @return array{status: 'not_found'}|array{status: 'self_disable'}|array{status: 'updated', row: \stdClass}
     */
    public function execute(int $userId, bool $isActive, ?string $actorEmail): array
    {
        return $this->directory->setActive($userId, $isActive, $actorEmail);
    }
}
