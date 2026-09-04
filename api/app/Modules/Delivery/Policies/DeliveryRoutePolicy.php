<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;

/**
 * Policy des tournées (BC-26-D05, issue #6294).
 *
 * Deny-by-default :
 *  - création / affectation / clôture : delivery.dispatcher + delivery.admin ;
 *  - consultation : dispatcher + admin + manager, ET le livreur pour SA
 *    propre tournée (driver_id = id de l'employé connecté) — le livreur ne
 *    voit jamais la tournée d'un collègue (test négatif cross-employé).
 *
 * Périmètre borné au tenant de l'acteur (company_id, fail-closed #3727).
 */
final class DeliveryRoutePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager()
            && in_array($actor->manager_role, ['principal', 'manager', 'rh'], true);
    }

    public function view(Employee $actor, DeliveryRoute $route): bool
    {
        if ($route->company_id !== $actor->company_id) {
            return false;
        }

        if ($this->canDispatch($actor) || $this->canRead($actor)) {
            return true;
        }

        // Livreur : accès limité à SA tournée (scope par employé connecté).
        return $actor->isEmployee()
            && $actor->status === 'active'
            && $route->driver_id === $actor->id;
    }

    /** Managers en lecture (principal/manager/rh) — parité delivery.manager. */
    private function canRead(Employee $actor): bool
    {
        return $actor->isManager()
            && in_array($actor->manager_role, ['principal', 'manager', 'rh'], true);
    }

    public function create(Employee $actor): bool
    {
        return $this->canDispatch($actor);
    }

    public function assign(Employee $actor, DeliveryRoute $route): bool
    {
        return $this->canDispatch($actor) && $route->company_id === $actor->company_id;
    }

    public function close(Employee $actor, DeliveryRoute $route): bool
    {
        return $this->canDispatch($actor) && $route->company_id === $actor->company_id;
    }

    public function canDispatch(Employee $actor): bool
    {
        return $actor->isManager()
            && in_array($actor->manager_role, ['principal', 'manager'], true);
    }
}
