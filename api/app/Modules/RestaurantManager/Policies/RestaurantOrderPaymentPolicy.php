<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-407 (#6194) — Policy des paiements de commande.
 *
 * L'encaissement est une opération serveur/caissier (spec §1.2) ; la
 * consultation est ouverte à tout employé authentifié du tenant (404 sûr
 * cross-tenant au niveau contrôleur).
 */
class RestaurantOrderPaymentPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantOrderPayment $payment): bool
    {
        return $payment->company_id === $actor->company_id
            && $this->canViewBranchResource($actor, $payment->order?->branch_id);
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canOperateBranchResource($actor, $branchId);
    }
}
