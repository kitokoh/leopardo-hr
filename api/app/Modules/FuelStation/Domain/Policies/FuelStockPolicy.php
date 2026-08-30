<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use App\Modules\FuelStation\Domain\Models\FuelTankDelivery;

/**
 * RBAC des stocks FuelStation (FUEL-009, #5803).
 *
 * - Manager (role=manager) : enregistrement des livraisons et pilotage des
 *   rapprochements (deny-by-default pour tout autre rôle).
 * - Employé (pompiste) : aucun accès d'administration — il ne lit ni ne
 *   modifie les stocks via ces endpoints (lecture via le dashboard manager).
 */
class FuelStockPolicy
{
    public function createDelivery(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function viewStocks(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function runReconciliation(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function viewAnyReconciliation(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function viewReconciliation(Employee $actor, FuelReconciliationRun $run): bool
    {
        return $actor->isManager();
    }

    public function viewDelivery(Employee $actor, FuelTankDelivery $delivery): bool
    {
        return $actor->isManager();
    }
}
