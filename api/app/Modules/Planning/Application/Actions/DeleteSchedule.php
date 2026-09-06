<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Infrastructure\Services\TenantCacheService;
use App\Modules\Planning\Domain\Models\Schedule;

/**
 * Cas d'usage : suppression d'un planning (horaire) par un manager.
 *
 * Consommé par `DELETE /api/v1/schedules/{schedule}`
 * (ScheduleController::destroy). La garde « un planning par défaut ne se
 * supprime pas » (422 SCHEDULE_DEFAULT_DELETE_FORBIDDEN) reste portée par le
 * contrôleur (règle HTTP) ; l'Action exécute la suppression et l'invalidation
 * des caches tenant (schedules + employees).
 */
class DeleteSchedule
{
    public function __construct(
        private readonly TenantCacheService $tenantCache,
    ) {}

    public function execute(Employee $actor, Schedule $schedule): void
    {
        $schedule->delete();

        $this->tenantCache->invalidateSchedules((string) $actor->company_id);
        $this->tenantCache->invalidateEmployees((string) $actor->company_id);
    }
}
