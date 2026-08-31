<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantHour;

/**
 * RESTO-304 (#6185) — Policy des horaires d'ouverture RestaurantManager.
 *
 * Création/modification/suppression : principal, rh ou manager — le manager
 * de salle (`manager`) ajuste les horaires de ses branches au quotidien
 * (permission `restaurant.manager`). Lecture : tout employé authentifié du
 * tenant — le périmètre reste borné par le scope `BelongsToCompany` + le
 * contrôleur (404 sûr cross-tenant, jamais un 403 qui révélerait
 * l'existence de la ressource sur un autre tenant).
 */
class RestaurantHourPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantHour $hour): bool
    {
        return $hour->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, RestaurantHour $hour): bool
    {
        return $this->create($actor) && $hour->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, RestaurantHour $hour): bool
    {
        return $this->update($actor, $hour);
    }
}
