<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-302 (#6183) — Policy des catégories de produits RestaurantManager.
 *
 * Lecture : tout employé authentifié du tenant (le périmètre reste borné
 * par le scope `BelongsToCompany` + le contrôleur : 404 sûr cross-tenant,
 * jamais un 403 qui révélerait l'existence de la ressource sur un autre
 * tenant). Écriture : manager principal ou RH uniquement.
 */
class RestaurantCategoryPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantCategory $category): bool
    {
        return $category->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $category->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canManageBranchResource($actor, $branchId);
    }

    public function update(Employee $actor, RestaurantCategory $category): bool
    {
        return $category->company_id === $actor->company_id
            && $this->canManageBranchResource($actor, $category->branch_id);
    }

    public function delete(Employee $actor, RestaurantCategory $category): bool
    {
        return $this->update($actor, $category);
    }
}
