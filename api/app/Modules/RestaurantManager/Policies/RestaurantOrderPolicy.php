<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-402 (#6189) — Policy des commandes restaurant.
 *
 * Création/modification : serveur/caissier ou supérieur (persona
 * « prise de commande, service, encaissement », spec §1.2). Lecture : tout
 * employé authentifié du tenant (périmètre borné par `company_id`, 404 sûr
 * cross-tenant au niveau contrôleur). Les transitions d'état sont tranchées
 * par `RestaurantOrderTransitionPolicy`/actions dédiées (RESTO-404).
 */
class RestaurantOrderPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantOrder $order): bool
    {
        return $order->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $order->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canOperateBranchResource($actor, $branchId);
    }

    public function update(Employee $actor, RestaurantOrder $order): bool
    {
        return $order->company_id === $actor->company_id
            && $this->canOperateBranchResource($actor, $order->branch_id);
    }
}
