<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCount;

/**
 * RESTO-504 (#6203) — Policy des inventaires physiques.
 *
 * Création/saisie/soumission : `principal`/`rh` (gestion du stock) ; le
 * manager de salle peut lire. L'approbation est réservée à la direction
 * (`principal`/`rh` avec `restaurant.manage`).
 */
class RestaurantInventoryCountPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantInventoryCount $count): bool
    {
        return $count->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, RestaurantInventoryCount $count): bool
    {
        return $this->create($actor) && $count->company_id === $actor->company_id;
    }

    public function approve(Employee $actor, RestaurantInventoryCount $count): bool
    {
        return $this->update($actor, $count);
    }
}
