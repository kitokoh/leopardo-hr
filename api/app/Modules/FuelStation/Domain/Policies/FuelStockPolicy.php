<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;

/**
 * #5803/#5805 — RBAC stocks & rapprochement (FUEL-009/FUEL-011) : deny-by-default.
 *
 * Manager principal/rh : mouvements, comptages et rapports d'écart.
 * Opérateur : lecture seule des niveaux (référentiel).
 */
class FuelStockPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelStockReconciliation $reconciliation): bool
    {
        return $actor->isManager()
            && (string) $reconciliation->company_id === (string) $actor->company_id;
    }

    public function recordMovement(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function reconcile(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
