<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;

/**
 * RESTO-303 (#6184) — Policy des ingrédients RestaurantManager.
 *
 * Lecture : tout employé authentifié du tenant (le périmètre reste borné
 * par le scope `BelongsToCompany` + le contrôleur : 404 sûr cross-tenant,
 * jamais un 403 qui révélerait l'existence de la ressource sur un autre
 * tenant). Écriture : manager principal ou RH uniquement.
 */
class RestaurantIngredientPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantIngredient $ingredient): bool
    {
        return $ingredient->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, RestaurantIngredient $ingredient): bool
    {
        return $this->create($actor) && $ingredient->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, RestaurantIngredient $ingredient): bool
    {
        return $this->update($actor, $ingredient);
    }
}
