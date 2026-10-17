<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelShiftAssignment;

/**
 * RBAC des shifts et affectations (FUEL-005) — gestion manager,
 * croisée avec l'accès ressource-scopé par station (#7600).
 */
class FuelShiftPolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function view(Employee $actor, FuelShift $shift): bool
    {
        return $actor->isManager()
            && $this->canViewScopedResource($actor, 'fuel_station', $shift->station_id ?? null, true);
    }

    public function create(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function update(Employee $actor, FuelShift $shift): bool
    {
        return $actor->isManager()
            && $this->canManageScopedResource($actor, 'fuel_station', $shift->station_id ?? null, true);
    }

    public function delete(Employee $actor, FuelShift $shift): bool
    {
        return $this->update($actor, $shift);
    }

    public function assign(Employee $actor, FuelShift $shift): bool
    {
        return $this->update($actor, $shift);
    }

    public function viewAssignments(Employee $actor, FuelShift $shift): bool
    {
        return $this->view($actor, $shift);
    }

    public function cancelAssignment(Employee $actor, FuelShiftAssignment $assignment): bool
    {
        return $actor->isManager()
            && $this->canManageScopedResource($actor, 'fuel_station', null, true);
    }
}
