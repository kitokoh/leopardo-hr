<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;

/**
 * RBAC des tâches de maintenance FuelStation (FUEL-010, #5804).
 * Gestion réservée au manager (deny-by-default pour les pompistes).
 */
class FuelMaintenanceTaskPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelMaintenanceTask $task): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelMaintenanceTask $task): bool
    {
        return $actor->isManager();
    }

    public function delete(Employee $actor, FuelMaintenanceTask $task): bool
    {
        return $actor->isManager();
    }
}
