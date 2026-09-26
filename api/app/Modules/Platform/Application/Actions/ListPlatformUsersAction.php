<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use App\Modules\Platform\Infrastructure\Services\PlatformUserDirectoryService;

/**
 * Liste paginée des utilisateurs plateforme (schéma public) — cas d'usage de
 * lecture extrait de PlatformUsersController (issue #6569, audit DDD M1).
 * Délègue l'accès données à PlatformUserDirectoryService (Infrastructure,
 * pattern Action Application → Service Infrastructure, ADR-0020).
 */
final class ListPlatformUsersAction
{
    public function __construct(
        private readonly PlatformUserDirectoryService $directory,
    ) {}

    /**
     * @return array{
     *   rows: array<int, \stdClass>,
     *   meta: array{current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function execute(int $perPage, string $search, string $status, string $sortBy, string $sortDir): array
    {
        return $this->directory->list($perPage, $search, $status, $sortBy, $sortDir);
    }
}
