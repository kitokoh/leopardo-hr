<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Modules\Attendance\Application\DTOs\GeoEventDTO;
use App\Modules\Attendance\Domain\Models\GeoAttendanceSession;
use App\Modules\Attendance\Infrastructure\Services\GeoSessionManager;

/**
 * Cas d'usage : traiter un événement zone_exit depuis le mobile.
 * Ferme la session GPS ouverte et met le statut à pending_validation.
 */
class ProcessGeoExit
{
    public function __construct(
        private readonly GeoSessionManager $sessionManager,
    ) {
    }

    public function handle(GeoEventDTO $dto): ?GeoAttendanceSession
    {
        return $this->sessionManager->closeSession($dto);
    }
}
