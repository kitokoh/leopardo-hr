<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;

/**
 * RBAC des stocks FuelStation (FUEL-009, #5803).
 *
 * - Manager : consultation et enregistrement des livraisons/ajustements.
 * - Employé (pompiste) : lecture seule du niveau de stock de sa station via
 *   les endpoints self-service — aucun accès à l'administration
 *   (deny-by-default).
 */
class FuelStockPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelStockReconciliation $reconciliation): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, FuelStockReconciliation $reconciliation): bool
    {
        return $actor->isManager();
    }
}
