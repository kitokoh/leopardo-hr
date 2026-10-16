<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;

/**
 * RBAC des sessions de caisse (FUEL-007) — la gestion appartient au
 * propriétaire (`opened_by`) et au manager, croisée avec l'accès
 * ressource-scopé par station (#7600).
 */
class FuelCashSessionPolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canViewScopedResource($actor, 'fuel_station', null);
    }

    public function view(Employee $actor, FuelCashSession $session): bool
    {
        if (! ($actor->isManager() || $session->opened_by === $actor->id)) {
            return false;
        }

        return $this->canViewScopedResource($actor, 'fuel_station', $session->station_id ?? null);
    }

    public function create(Employee $actor): bool
    {
        return $this->canOperateScopedResource($actor, 'fuel_station', null, true);
    }

    public function addMovement(Employee $actor, FuelCashSession $session): bool
    {
        return $session->opened_by === $actor->id
            && $this->canOperateScopedResource($actor, 'fuel_station', $session->station_id ?? null, true);
    }

    public function close(Employee $actor, FuelCashSession $session): bool
    {
        return $this->addMovement($actor, $session);
    }

    public function approve(Employee $actor, FuelCashSession $session): bool
    {
        return $actor->isManager()
            && $this->canManageScopedResource($actor, 'fuel_station', $session->station_id ?? null, true);
    }
}
