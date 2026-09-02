<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelRentalBooking;

/**
 * TRAVEL-320 (#6050) — Policy des réservations de location TravelAgency.
 *
 * Lecture ouverte à tout employé du tenant ; création/annulation réservées
 * à `travel.manage` (principal/rh/manager).
 */
class TravelRentalBookingPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelRentalBooking $booking): bool
    {
        return $booking->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelRentalBooking $booking): bool
    {
        return $this->create($actor) && $booking->company_id === $actor->company_id;
    }
}
