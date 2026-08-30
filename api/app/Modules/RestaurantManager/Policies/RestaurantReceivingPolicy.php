<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReceiving;

/**
 * RESTO-503 (#6202) — Policy des réceptions de marchandises.
 *
 * Réceptionner : gérant, RH ou manager de salle (achats opérationnels).
 * Lecture : tout employé authentifié du tenant (404 sûr cross-tenant).
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
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }
}
