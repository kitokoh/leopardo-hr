<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;

/**
 * TRAVEL-312..315 (#6042..#6045) — Policy des réservations TravelAgency.
 *
 * Lecture ouverte à tout employé du tenant ; création réservée à
 * `travel.manage` (principal/rh/manager) pour la vente guichet. Les
 * transitions (confirm/cancel/refund) suivent la même règle.
 */
class TravelBookingPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelBooking $booking): bool
    {
        return $booking->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        // #EVAL — la matrice RBAC (docs/security/TRAVEL_RBAC_MATRIX.md) et la
        // spec D9 déclarent `travel.agent` = « vente guichet / caisse (lecture
        // + création de réservation) », et l'app mobile agent
        // (front/mobile_apps/leopardo_travel_agent) vend au guichet via
        // `POST /travel/bookings`. Or cette Policy n'autorisait QUE les
        // managers : un vendeur/contrôleur recevait 403 et ne pouvait rien
        // vendre. On honore le grant `travel.agent` installé par le manifest
        // (SolutionPermissionInstaller), sans ouvrir l'écriture aux autres
        // employés (fail-closed).
        return $actor->hasManagerRole('principal', 'rh')
            || $actor->hasModuleGrant('travel.agent');
    }

    public function update(Employee $actor, TravelBooking $booking): bool
    {
        return $this->create($actor) && $booking->company_id === $actor->company_id;
    }
}
