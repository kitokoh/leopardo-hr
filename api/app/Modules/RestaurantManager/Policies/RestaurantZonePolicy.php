<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantZone;

/**
 * RESTO-301 (#6182) — Policy des zones du plan de salle RestaurantManager.
 *
 * Création/modification/suppression : principal, rh ou manager — le manager
 * de salle (`manager`) gère le plan de salle au quotidien. Lecture : tout
 * employé authentifié du tenant — le périmètre reste borné par le scope
 * `BelongsToCompany` + le contrôleur (404 sûr cross-tenant, jamais un 403
 * qui révélerait l'existence de la ressource sur un autre tenant).
 */
class RestaurantZonePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantZone $zone): bool
    {
        return $zone->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, RestaurantZone $zone): bool
    {
        return $this->create($actor) && $zone->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, RestaurantZone $zone): bool
    {
        return $this->update($actor, $zone);
    }
}
