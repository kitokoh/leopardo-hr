<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-501 (#6200) — Policy des niveaux de stock.
 *
 * Lecture : tout employé authentifié du tenant. Écriture (création, seuils,
 * coût moyen) : `principal`/`rh` — le serveur ne manipule pas les seuils.
 */
class RestaurantStockLevelPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantStockLevel $level): bool
    {
        return $level->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $level->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canManageBranchResource($actor, $branchId);
    }

    public function update(Employee $actor, RestaurantStockLevel $level): bool
    {
        return $level->company_id === $actor->company_id
            && $this->canManageBranchResource($actor, $level->branch_id);
    }

    public function delete(Employee $actor, RestaurantStockLevel $level): bool
    {
        return $this->update($actor, $level);
    }
}
