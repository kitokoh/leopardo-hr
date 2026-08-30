<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelSale;

/**
 * RBAC des ventes FuelStation (FUEL-008, #5802).
 *
 * - Tout employé authentifié (pompiste) : enregistrer une vente et voir
 *   SES ventes.
 * - Manager : voir toutes les ventes du tenant.
 */
class FuelSalePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelSale $sale): bool
    {
        return $actor->isManager() || $sale->employee_id === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return true;
    }
}
