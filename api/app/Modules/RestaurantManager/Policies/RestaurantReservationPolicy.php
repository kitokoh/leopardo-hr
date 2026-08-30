<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;

/**
 * RESTO-601 (#6206) — Policy des réservations.
 *
 * Création/modification : serveur, manager de salle ou supérieur (persona
 * « réservations, affectation des tables »). Lecture : tout employé
 * authentifié du tenant (404 sûr cross-tenant au niveau contrôleur).
 */
class RestaurantReservationPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantReservation $reservation): bool
    {
        return $reservation->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'server');
    }

    public function update(Employee $actor, RestaurantReservation $reservation): bool
    {
        return $this->create($actor) && $reservation->company_id === $actor->company_id;
    }
}
