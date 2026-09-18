<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSupplier;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;

/**
 * RESTO-305 (#6186) — Policy des fournisseurs RestaurantManager.
 *
 * Création/modification/suppression : principal ou rh — la relation
 * fournisseur engage des achats (permission `restaurant.manage`), le
 * manager de salle n'y a pas accès en écriture. Lecture : tout employé
 * authentifié du tenant — le périmètre reste borné par le scope
 * `BelongsToCompany` + le contrôleur (404 sûr cross-tenant, jamais un 403
 * qui révélerait l'existence de la ressource sur un autre tenant).
 */
class RestaurantSupplierPolicy
{
    use ChecksRestaurantBranchAccess;

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantSupplier $supplier): bool
    {
        return $supplier->company_id === $actor->company_id;
    }

    public function create(Employee $actor, int|string|null $branchId = null): bool
    {
        return $this->canManageBranchResource($actor, $branchId);
    }

    public function update(Employee $actor, RestaurantSupplier $supplier): bool
    {
        return $supplier->company_id === $actor->company_id
            && $this->canManageBranchResource($actor, null);
    }

    public function delete(Employee $actor, RestaurantSupplier $supplier): bool
    {
        return $this->update($actor, $supplier);
    }
}
