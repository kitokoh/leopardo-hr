<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Application\Actions;

use App\Modules\SmartAttendance\Application\DTOs\GeoEventDTO;
use App\Modules\SmartAttendance\Domain\Models\GeoAttendanceSession;
use App\Modules\SmartAttendance\Infrastructure\Services\GeoSessionManager;

/**
 * Cas d'usage : traiter un événement zone_enter depuis le mobile.
 * Crée une nouvelle session GPS avec statut detected → pending_validation.
 */
class ProcessGeoEntry
{
    public function __construct(
        private readonly GeoSessionManager $sessionManager,
    ) {}

    public function handle(GeoEventDTO $dto): GeoAttendanceSession
    {
        return $this->sessionManager->openSession($dto);
    }
}
