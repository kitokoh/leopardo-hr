<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-409 (#6196) — Policy des sessions d'occupation de table.
 *
 * Ouverture/clôture : serveur, manager de salle ou supérieur (personas
 * « prise de commande, service » et « plan de salle ») ; lecture : tout
 * employé authentifié du tenant (404 sûr cross-tenant au niveau contrôleur).
 */
class RestaurantTableSessionPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantTableSession $session): bool
    {
        return $session->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $session->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canOperateBranchResource($actor, $branchId);
    }

    public function close(Employee $actor, RestaurantTableSession $session): bool
    {
        return $session->company_id === $actor->company_id
            && $this->canOperateBranchResource($actor, $session->branch_id);
    }
}
