<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryZone;

/**
 * RESTO-604 (#6209) — Policy des zones de livraison.
 *
 * Lecture : tout employé du tenant. Écriture (frais, minimums) :
 * `principal`/`rh` — la tarification est une décision de gestion.
 */
class RestaurantDeliveryZonePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantDeliveryZone $zone): bool
    {
        return $zone->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, RestaurantDeliveryZone $zone): bool
    {
        return $this->create($actor) && $zone->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, RestaurantDeliveryZone $zone): bool
    {
        return $this->update($actor, $zone);
    }
}
