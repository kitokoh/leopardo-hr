<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;

/**
 * RESTO-605 (#6210) — Policy des livreurs RestaurantManager.
 *
 * Création/modification/suppression : principal, rh ou manager (le référentiel
 * livreurs est du pilotage opérationnel) ; lecture : tout employé authentifié
 * du tenant. Mismatch cross-tenant → 404 (jamais 403) côté contrôleur.
 */
class RestaurantDeliveryRiderPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantDeliveryRider $rider): bool
    {
        return $rider->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, RestaurantDeliveryRider $rider): bool
    {
        return $this->create($actor) && $rider->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, RestaurantDeliveryRider $rider): bool
    {
        return $this->update($actor, $rider);
    }
}
