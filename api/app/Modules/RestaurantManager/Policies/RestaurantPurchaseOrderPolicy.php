<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;

/**
 * RESTO-502 (#6201) — Policy des bons de commande fournisseurs.
 *
 * Lecture : tout employé du tenant. Écriture (création, transitions) :
 * `principal`/`rh` — les achats restent une décision de gestion.
 */
class RestaurantPurchaseOrderPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantPurchaseOrder $order): bool
    {
        return $order->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, RestaurantPurchaseOrder $order): bool
    {
        return $this->create($actor) && $order->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, RestaurantPurchaseOrder $order): bool
    {
        return $this->update($actor, $order);
    }
}
