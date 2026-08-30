<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;

/**
 * RESTO-301 (#6182) — Policy des succursales RestaurantManager.
 *
 * Création/modification/suppression : principal ou rh (le manager de salle
 * n'ouvre pas de succursale — il gère le plan de salle, cf.
 * RestaurantZonePolicy). Lecture : tout employé authentifié du tenant — le
 * périmètre reste borné par le scope `BelongsToCompany` + le contrôleur
 * (404 sûr cross-tenant, jamais un 403 qui révélerait l'existence de la
 * ressource sur un autre tenant).
 */
class RestaurantBranchPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantBranch $branch): bool
    {
        return $branch->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, RestaurantBranch $branch): bool
    {
        return $this->create($actor) && $branch->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, RestaurantBranch $branch): bool
    {
        return $this->update($actor, $branch);
    }
}
