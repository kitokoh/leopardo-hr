<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenuItem;

/**
 * RESTO-304 (#6185) — Policy des lignes de menu RestaurantManager.
 *
 * Même périmètre que `RestaurantMenuPolicy` : une ligne de menu n'existe
 * que dans le contexte du menu parent (permission `restaurant.manager`).
 * Lecture : tout employé authentifié du tenant — le périmètre reste borné
 * par le scope `BelongsToCompany` + le contrôleur (404 sûr cross-tenant,
 * jamais un 403 qui révélerait l'existence de la ressource sur un autre
 * tenant).
 */
class RestaurantMenuItemPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantMenuItem $item): bool
    {
        return $item->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, RestaurantMenuItem $item): bool
    {
        return $this->create($actor) && $item->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, RestaurantMenuItem $item): bool
    {
        return $this->update($actor, $item);
    }
}
