<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyProgram;

/**
 * RESTO-606 (#6211) — Policies du programme de fidélité.
 *
 * Configuration du programme et gestion des points : `principal`/`rh`/`manager`.
 */
class RestaurantLoyaltyPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, $model): bool
    {
        return $model->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, $model): bool
    {
        return $this->create($actor) && $model->company_id === $actor->company_id;
    }
}
