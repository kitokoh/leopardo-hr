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

    /**
     * #8578 (D6) — transition de cycle de vie d'une tâche.
     *
     * Cette méthode manquait ici : `authorize('transitionTask', $task)` résout
     * la policy via `FuelMaintenanceTask::class`, donc
     * `FuelMaintenanceTaskPolicy` — qui n'avait pas la méthode. Laravel répond
     * alors 403 à TOUT LE MONDE, y compris au manager propriétaire. Le service
     * n'était jamais atteint : un acteur autorisé recevait 403 là où il devait
     * recevoir 422 sur une transition illégale.
     *
     * On n'exige pas `canManageScopedResource` quand la tâche n'a pas de
     * station : ce cas retombe sur « principal uniquement », ce qui refuserait
     * à un manager légitime d'avancer une tâche préventive company-wide alors
     * qu'il n'y a aucune ressource à restreindre.
     */
    public function transitionTask(Employee $actor, FuelMaintenanceTask $task): bool
    {
        if ($task->company_id !== (string) $actor->company_id) {
            return false;
        }

        if (! $actor->isManager()) {
            return false;
        }

        if ($task->station_id === null) {
            return true;
        }

        return $this->canManageScopedResource($actor, 'fuel_station', $task->station_id, true);
    }
}
