<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;

/**
 * RESTO-409 (#6196) — Policy des sessions d'occupation de table.
 *
 * Ouverture/clôture : serveur, manager de salle ou supérieur (personas
 * « prise de commande, service » et « plan de salle ») ; lecture : tout
 * employé authentifié du tenant (404 sûr cross-tenant au niveau contrôleur).
 */
class RestaurantTableSessionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantTableSession $session): bool
    {
        return $session->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'server');
    }

    public function close(Employee $actor, RestaurantTableSession $session): bool
    {
        return $this->create($actor) && $session->company_id === $actor->company_id;
    }
}
