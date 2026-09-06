<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Infrastructure\Services\TenantCacheService;
use App\Modules\Planning\Domain\Models\Schedule;

/**
 * Cas d'usage : mise à jour d'un planning (horaire) par un manager.
 *
 * Consommé par `PUT|PATCH /api/v1/schedules/{schedule}`
 * (ScheduleController::update). L'unicité du planning par défaut est
 * préservée : passer `is_default` déchoit l'ancien planning par défaut du
 * tenant (hors celui en cours de mise à jour).
 */
class UpdateSchedule
{
    public function __construct(
        private readonly TenantCacheService $tenantCache,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, Schedule $schedule, array $validated): Schedule
    {
        if (! empty($validated['is_default'])) {
            Schedule::query()
                ->where('company_id', $actor->company_id)
                ->where('id', '!=', $schedule->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $schedule->update($validated);

        $this->tenantCache->invalidateSchedules((string) $actor->company_id);
        $this->tenantCache->invalidateEmployees((string) $actor->company_id);

        return $schedule->fresh() ?? $schedule;
    }
}
