<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;

/**
 * RESTO-501 (#6200) — Policy des mouvements de stock.
 *
 * Création d'un ajustement/transfert : gérant, RH ou manager de salle
 * (opérations stock quotidiennes). Lecture : tout employé authentifié du
 * tenant (404 sûr cross-tenant au niveau contrôleur).
 */
class RestaurantInventoryMovementPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RestaurantInventoryMovement $movement): bool
    {
        return $movement->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }
}
