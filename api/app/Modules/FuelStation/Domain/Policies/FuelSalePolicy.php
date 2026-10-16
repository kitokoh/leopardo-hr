<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelSale;

/**
 * RBAC des ventes (FUEL-008, #5802) — enregistrement ouvert à tout employé
 * opérationnel, lecture manager ou vendeur, croisée avec l'accès
 * ressource-scopé par station (#7600).
 */
class FuelSalePolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function view(Employee $actor, FuelSale $sale): bool
    {
        if (! ($actor->isManager() || $sale->employee_id === $actor->id)) {
            return false;
        }

        return $this->canViewScopedResource($actor, 'fuel_station', $sale->station_id);
    }

    public function create(Employee $actor): bool
    {
        return $this->canOperateScopedResource($actor, 'fuel_station', null, true);
    }
}
