<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Infrastructure\Services\TenantCacheService;
use App\Modules\Planning\Domain\Models\Schedule;

/**
 * Cas d'usage : création d'un planning (horaire) par un manager.
 *
 * Consommé par `POST /api/v1/schedules` (ScheduleController::store).
 * L'autorisation d'écriture (manager) est portée par la StoreScheduleRequest
 * (authorize) et le contrôleur ; l'Action ne reçoit que des champs validés.
 */
class CreateSchedule
{
    public function __construct(
        private readonly TenantCacheService $tenantCache,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, array $validated): Schedule
    {
        if (! empty($validated['is_default'])) {
            Schedule::query()
                ->where('company_id', $actor->company_id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $schedule = Schedule::create([
            'company_id' => $actor->company_id,
            'work_days' => $validated['work_days'] ?? [1, 2, 3, 4, 5],
            ...$validated,
        ]);

        $this->tenantCache->invalidateSchedules((string) $actor->company_id);
        $this->tenantCache->invalidateEmployees((string) $actor->company_id);

        return $schedule;
    }
}
