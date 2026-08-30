<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;

/**
 * RESTO-304 (#6185) — Policy des menus RestaurantManager.
 *
 * Création/modification/suppression : principal, rh ou manager — le manager
 * de salle (`manager`) compose les menus de la carte au quotidien
 * (permission `restaurant.manager`). Lecture : tout employé authentifié du
 * tenant — le périmètre reste borné par le scope `BelongsToCompany` + le
 * contrôleur (404 sûr cross-tenant, jamais un 403 qui révélerait
 * l'existence de la ressource sur un autre tenant).
 */
class RestaurantMenuPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantMenu $menu): bool
    {
        return $menu->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, RestaurantMenu $menu): bool
    {
        return $this->create($actor) && $menu->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, RestaurantMenu $menu): bool
    {
        return $this->update($actor, $menu);
    }
}
