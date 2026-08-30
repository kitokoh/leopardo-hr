<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;

/**
 * RESTO-607 (#6212) — Policy des promotions.
 *
 * Lecture : tout employé du tenant. Écriture : `principal`/`rh`/`manager`.
 */
class RestaurantPromotionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantPromotion $promo): bool
    {
        return $promo->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, RestaurantPromotion $promo): bool
    {
        return $this->create($actor) && $promo->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, RestaurantPromotion $promo): bool
    {
        return $this->update($actor, $promo);
    }
}
