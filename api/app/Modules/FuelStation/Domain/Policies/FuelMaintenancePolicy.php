<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;

/**
 * RBAC incidents & maintenance FuelStation (FUEL-010, #5804).
 *
 * - Manager : gestion complète (création, workflow, tâches, pièces jointes).
 * - Employé (pompiste) : deny-by-default — il remonte un incident via son
 *   manager ou l'interface dédiée, jamais en écriture directe.
 */
class FuelMaintenancePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelIncident|FuelMaintenanceTask $resource): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function transition(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager();
    }

    public function attach(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager();
    }

    public function manageTask(Employee $actor, FuelMaintenanceTask $task): bool
    {
        return $actor->isManager();
    }
}
