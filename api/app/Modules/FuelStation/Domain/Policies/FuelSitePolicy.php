<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelSite;

/**
 * RBAC des sites d'une station (FUEL-002) — lecture ouverte au tenant,
 * écriture manager, croisée avec l'accès ressource-scopé (#7600).
 */
class FuelSitePolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FuelSite $site): bool
    {
        return $site->company_id === (string) $actor->company_id
            && $this->canViewScopedResource($actor, 'fuel_station', $site->station_id ?? null);
    }

    public function create(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function update(Employee $actor, FuelSite $site): bool
    {
        return $actor->isManager()
            && $site->company_id === (string) $actor->company_id
            && $this->canManageScopedResource($actor, 'fuel_station', $site->station_id ?? null, true);
    }
}
