<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelIncidentAttachment;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;

/**
 * RBAC incidents/maintenance FuelStation (FUEL-010, #5804).
 *
 * Deny-by-default :
 *  - manager : tout (signaler, assigner, traiter, résoudre, clôturer,
 *    tâches, pièces jointes) — permissions par site via le tenant-scope
 *    (un manager ne voit que les incidents de SON tenant) ;
 *  - pompiste (opérateur) : signaler un incident + voir les incidents
 *    qu'il a signalés ou dont il est assigné — jamais ceux des autres.
 */
class FuelIncidentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager()
            || $incident->reported_by === $actor->id
            || $incident->assigned_to === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return true;
    }

    public function assign(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager() || $incident->assigned_to === $actor->id;
    }

    public function resolve(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager() || $incident->assigned_to === $actor->id;
    }

    public function close(Employee $actor, FuelIncident $incident): bool
    {
        return $actor->isManager();
    }

    public function manageTask(Employee $actor, ?FuelMaintenanceTask $task = null): bool
    {
        return $actor->isManager();
    }

    public function manageAttachment(Employee $actor, FuelIncidentAttachment $attachment): bool
    {
        return $actor->isManager() || $attachment->uploaded_by === $actor->id;
    }
}
