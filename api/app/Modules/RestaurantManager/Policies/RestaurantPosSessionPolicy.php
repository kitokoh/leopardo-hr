<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

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
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantPosSession $session): bool
    {
        return $session->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $session->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canOperateBranchResource($actor, $branchId);
    }

    public function close(Employee $actor, RestaurantPosSession $session): bool
    {
        return $session->company_id === $actor->company_id
            && $this->canOperateBranchResource($actor, $session->branch_id);
    }
}
