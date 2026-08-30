<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelCustomer;

/**
 * RBAC des clients FuelStation (FUEL-016, #5810).
 * Gestion (CRUD, visites, consentement) réservée au manager — les données
 * clients (email/téléphone) ne sont jamais exposées aux pompistes
 * (deny-by-default).
 */
class FuelCustomerPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelCustomer $customer): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelCustomer $customer): bool
    {
        return $actor->isManager();
    }
}
