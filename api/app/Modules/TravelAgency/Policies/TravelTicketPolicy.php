<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;

/**
 * TRAVEL-317 (#6047) — Policy des billets TravelAgency.
 *
 * Check-in réservé à un rôle manager (embarquement/contrôle) ; lecture
 * ouverte à tout employé du tenant.
 */
class TravelTicketPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelTicket $ticket): bool
    {
        return $ticket->company_id === $actor->company_id;
    }

    public function checkIn(Employee $actor, TravelTicket $ticket): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager')
            && $ticket->company_id === $actor->company_id;
    }
}
