<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;

/**
 * TRAVEL-811 (#6101) — Policy des comptes fidélité.
 *
 * La gestion fidélité (opt-in/opt-out, récompenses) est réservée aux rôles
 * de vente (travel.manage) — le contact n'a pas de session sur cette API.
 */
class TravelLoyaltyPolicy
{
    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }
}
