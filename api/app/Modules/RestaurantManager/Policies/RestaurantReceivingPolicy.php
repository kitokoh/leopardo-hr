<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReceiving;

/**
 * RESTO-503 (#6202) — Policy des réceptions de marchandises.
 *
 * Lecture : tout employé du tenant. Écriture : `principal`/`rh` — la
 * réception valorise le stock, c'est une décision de gestion.
 */
class RestaurantReceivingPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantReceiving $receiving): bool
    {
        return $receiving->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }
}
