<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;

/**
 * RESTO-502 (#6201) — Policy des bons de commande fournisseurs.
 *
 * Création/modification : gérant, RH ou manager de salle (achats
 * opérationnels). Lecture : tout employé authentifié du tenant (404 sûr
 * cross-tenant au niveau contrôleur).
 */
class RestaurantPurchaseOrderPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantPurchaseOrder $po): bool
    {
        return $po->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, RestaurantPurchaseOrder $po): bool
    {
        return $this->create($actor) && $po->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, RestaurantPurchaseOrder $po): bool
    {
        return $this->update($actor, $po);
    }
}
