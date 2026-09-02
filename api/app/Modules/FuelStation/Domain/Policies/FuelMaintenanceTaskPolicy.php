<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;

/**
 * RBAC des tâches de maintenance (FUEL-010, #5804). deny-by-default :
 * gestion manager, lecture pour tout employé du tenant.
 */
class FuelMaintenanceTaskPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FuelMaintenanceTask $task): bool
    {
        return $task->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelMaintenanceTask $task): bool
    {
        return $actor->isManager() && $task->company_id === (string) $actor->company_id;
    }
}
