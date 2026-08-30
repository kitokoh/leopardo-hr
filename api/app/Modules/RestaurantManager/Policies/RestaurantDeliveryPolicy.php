<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;

/**
 * RESTO-605 (#6210) — Policy des livraisons.
 *
 * Lecture : tout employé du tenant. Écriture (cycle) : `principal`/`rh`/
 * `manager` ; le livreur (`rider`) lit ses tournées.
 */
class RestaurantDeliveryPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantDelivery $delivery): bool
    {
        return $delivery->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, RestaurantDelivery $delivery): bool
    {
        return $this->create($actor) && $delivery->company_id === $actor->company_id;
    }
}
