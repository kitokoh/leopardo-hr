<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-302 (#6183) — Policy des liens recette produit/ingrédient.
 *
 * `view` porte sur le produit parent (le lien n'a de sens que dans le
 * contexte de la recette de ce produit) ; l'écriture est réservée aux
 * managers principal/RH. Le contrôleur vérifie en amont le 404 sûr
 * cross-tenant sur le produit parent et sur le lien.
 */
class RestaurantProductIngredientPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantProductIngredient $link): bool
    {
        $product = $link->product;

        return $product !== null
            && $product->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $product->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canManageBranchResource($actor, $branchId);
    }

    public function update(Employee $actor, RestaurantProductIngredient $link): bool
    {
        return $link->company_id === $actor->company_id
            && $this->canManageBranchResource($actor, $link->product?->branch_id);
    }

    public function delete(Employee $actor, RestaurantProductIngredient $link): bool
    {
        return $this->update($actor, $link);
    }
}
