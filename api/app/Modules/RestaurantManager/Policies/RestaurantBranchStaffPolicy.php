<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranchStaff;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * #7909 — Policy des affectations staff ↔ succursale restaurant.
 *
 * Affecter/retirer un employé d'une succursale est un geste de GESTION
 * (`manage` sur la succursale via le RBAC ressource-scopé #7599, fallback
 * historique principal/rh tant qu'aucune assignation `restaurant_branch`
 * n'existe). Lecture : tout employé authentifié du tenant — le périmètre
 * reste borné par le scope `BelongsToCompany` + le contrôleur (404 sûr
 * cross-tenant, jamais un 403 qui révélerait l'existence de la ressource).
 */
class RestaurantBranchStaffPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantBranchStaff $assignment): bool
    {
        return $assignment->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $assignment->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canManageBranchResource($actor, $branchId);
    }

    public function update(Employee $actor, RestaurantBranchStaff $assignment): bool
    {
        return $assignment->company_id === $actor->company_id
            && $this->canManageBranchResource($actor, $assignment->branch_id);
    }

    public function delete(Employee $actor, RestaurantBranchStaff $assignment): bool
    {
        return $this->update($actor, $assignment);
    }
}
