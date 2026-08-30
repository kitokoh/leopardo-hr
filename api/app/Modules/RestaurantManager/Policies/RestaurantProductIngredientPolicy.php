<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;

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
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantProductIngredient $link): bool
    {
        $product = $link->product;

        return $product !== null && $product->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, RestaurantProductIngredient $link): bool
    {
        return $this->create($actor) && $link->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, RestaurantProductIngredient $link): bool
    {
        return $this->update($actor, $link);
    }
}
