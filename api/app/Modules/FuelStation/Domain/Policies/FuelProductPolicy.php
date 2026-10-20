<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelProduct;

/**
 * RBAC du catalogue produits (FUEL-003, #5797). Lecture ouverte au tenant,
 * écriture manager — croisée avec l'accès ressource-scopé (#7600).
 */
class FuelProductPolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, FuelProduct $product): bool
    {
        return $product->company_id === (string) $actor->company_id
            && $this->canViewScopedResource($actor, 'fuel_station', $product->station_id ?? null);
    }

    public function create(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function update(Employee $actor, FuelProduct $product): bool
    {
        return $actor->isManager()
            && $product->company_id === (string) $actor->company_id
            && $this->canManageScopedResource($actor, 'fuel_station', $product->station_id ?? null, true);
    }
}
