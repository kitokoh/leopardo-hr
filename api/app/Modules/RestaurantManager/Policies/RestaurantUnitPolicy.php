<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantUnit;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-303 (#6184) — Policy des unités de mesure RestaurantManager.
 *
 * Lecture : tout employé authentifié du tenant (le périmètre reste borné
 * par le scope `BelongsToCompany` + le contrôleur : 404 sûr cross-tenant,
 * jamais un 403 qui révélerait l'existence de la ressource sur un autre
 * tenant). Écriture : manager principal ou RH uniquement.
 */
class RestaurantUnitPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantUnit $unit): bool
    {
        return $unit->company_id === $actor->company_id;
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canManageBranchResource($actor, $branchId);
    }

    public function update(Employee $actor, RestaurantUnit $unit): bool
    {
        return $unit->company_id === $actor->company_id
            && $this->canManageBranchResource($actor, null);
    }

    public function delete(Employee $actor, RestaurantUnit $unit): bool
    {
        return $this->update($actor, $unit);
    }
}
