<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;

/**
 * RESTO-605 (#6210) — Policy des livraisons RestaurantManager.
 *
 * Le cycle de livraison (création, transitions) est du pilotage opérationnel
 * de la salle : réservé au manager/principal/rh (restaurant.manager) ; la
 * lecture reste ouverte à tout employé authentifié du tenant. Le livreur
 * (`restaurant.rider`) est un rôle de lecture/consultation dans cette
 * version — l'affectation reste validée par la salle.
 */
class RestaurantDeliveryPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantDelivery $delivery): bool
    {
        return $delivery->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function transition(Employee $actor, RestaurantDelivery $delivery): bool
    {
        return $this->create($actor) && $delivery->company_id === $actor->company_id;
    }
}
