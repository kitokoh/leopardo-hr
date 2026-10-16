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
        return $actor->hasManagerRole('principal', 'rh')
            && $ticket->company_id === $actor->company_id;
    }

    /**
     * #8538 — habilitation au CONTRÔLE des billets (vérification QR).
     *
     * Même périmètre que le check-in (`travel.checkin`, matrice RBAC
     * `docs/security/TRAVEL_RBAC_MATRIX.md`) : le contrôle d'embarquement est
     * une opération d'exploitation, pas une lecture ouverte.
     */
    public function verifyAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    /**
     * #8544 — lecture de l'HISTORIQUE des impressions (émission + réimpressions).
     *
     * Même périmètre que le contrôle d'embarquement : c'est une vue
     * d'exploitation (qui a réimprimé quel billet, et combien de fois), pas une
     * lecture ouverte à tout employé du tenant.
     */
    public function viewPrintHistory(Employee $actor, TravelTicket $ticket): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $ticket->company_id === $actor->company_id;
    }
}
