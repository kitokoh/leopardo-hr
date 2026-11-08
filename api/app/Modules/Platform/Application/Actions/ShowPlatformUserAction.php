<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use App\Modules\Platform\Infrastructure\Services\PlatformUserDirectoryService;

/**
 * Détail d'un utilisateur plateforme (schéma public) avec entreprise liée et
 * rôles tenant — cas d'usage extrait de PlatformUsersController (issue #6569,
 * audit DDD M1). Délègue à PlatformUserDirectoryService (Infrastructure).
 */
final class ShowPlatformUserAction
{
    public function __construct(
        private readonly PlatformUserDirectoryService $directory,
    ) {}

    public function execute(int $userId): ?\stdClass
    {
        return $this->directory->show($userId);
    }
}
