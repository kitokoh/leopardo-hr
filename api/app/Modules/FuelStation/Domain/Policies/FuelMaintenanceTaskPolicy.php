<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;

/**
 * RBAC des tâches de maintenance (FUEL-010) — croisé avec l'accès
 * ressource-scopé (#7600).
 */
class FuelMaintenanceTaskPolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FuelMaintenanceTask $task): bool
    {
        return $task->company_id === (string) $actor->company_id
            && $this->canViewScopedResource($actor, 'fuel_station', $task->station_id ?? null);
    }

    public function create(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function update(Employee $actor, FuelMaintenanceTask $task): bool
    {
        return $actor->isManager()
            && $task->company_id === (string) $actor->company_id
            && $this->canManageScopedResource($actor, 'fuel_station', $task->station_id ?? null, true);
    }
}
