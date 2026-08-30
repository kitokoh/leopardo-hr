<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelRoundTrip;

/**
 * TRAVEL-802 (#6093) — Policy des allers-retours combinés.
 *
 * Création réservée aux rôles de vente (travel.manage) comme les
 * réservations ; lecture ouverte au tenant.
 */
class TravelRoundTripPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelRoundTrip $roundTrip): bool
    {
        return $roundTrip->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }
}
