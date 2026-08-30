<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;

/**
 * RESTO-401 (#6188) — Policy des sessions de caisse POS.
 *
 * Ouverture : serveur/caissier ou supérieur (prise de poste en caisse).
 * Clôture : gérant / RH / manager de salle uniquement (persona « clôtures »
 * de la spec §1.2) + contrôle tenant (cross-tenant → 404 au niveau contrôleur).
 * Lecture : tout employé authentifié du tenant.
 */
class RestaurantPosSessionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantPosSession $session): bool
    {
        return $session->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'server');
    }

    public function close(Employee $actor, RestaurantPosSession $session): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager')
            && $session->company_id === $actor->company_id;
    }
}
