<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;

/**
 * RBAC des incidents et tâches de maintenance (FUEL-010, #5804).
 *
 * - Tout employé authentifié du tenant peut SIGNALER un incident
 *   (remontée terrain — pompiste inclus).
 * - Le manager (role=manager) gère le workflow complet : affectation,
 *   résolution, clôture, et CRUD des tâches de maintenance.
 * - Deny-by-default : aucun autre rôle ne peut transitionner ni créer de
 *   tâche ; l'isolation tenant reste assurée par le contrôleur (404).
 */
class FuelIncidentPolicy
{
    public function report(Employee $actor): bool
    {
        return true;
    }

    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager() || $incident->reported_by === $actor->id;
    }

    public function assign(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager();
    }

    public function resolve(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager();
    }

    public function close(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager();
    }

    public function createTask(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function viewAnyTask(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function viewTask(Employee $actor, FuelMaintenanceTask $task): bool
    {
        return $actor->isManager() || $task->assigned_to === $actor->id;
    }

    public function transitionTask(Employee $actor, FuelMaintenanceTask $task): bool
    {
        return $actor->isManager() || $task->assigned_to === $actor->id;
    }
}
