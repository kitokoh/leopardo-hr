<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;

/**
 * #5804/#5805 — RBAC tâches de maintenance (FUEL-010/FUEL-011) : deny-by-default.
 *
 * Manager : CRUD + achèvement. Opérateur : voir ses tâches assignées et les
 * achever.
 */
class FuelMaintenanceTaskPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FuelMaintenanceTask $task): bool
    {
        if ((string) $task->company_id !== (string) $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $task->assigned_to === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelMaintenanceTask $task): bool
    {
        return $actor->isManager() && (string) $task->company_id === (string) $actor->company_id;
    }

    public function complete(Employee $actor, FuelMaintenanceTask $task): bool
    {
        if ((string) $task->company_id !== (string) $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $task->assigned_to === $actor->id;
    }
}
