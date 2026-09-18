<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-501 (#6200) — Policy du journal des mouvements de stock.
 *
 * Lecture : tout employé du tenant. Écriture : `principal`/`rh` (le serveur
 * passe par les flux de vente, pas par un mouvement manuel libre).
 */
class RestaurantInventoryMovementPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantInventoryMovement $movement): bool
    {
        return $movement->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $movement->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canManageBranchResource($actor, $branchId);
    }
}
