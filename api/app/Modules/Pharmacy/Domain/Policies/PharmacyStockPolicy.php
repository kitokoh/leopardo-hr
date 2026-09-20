<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;

/**
 * RBAC du stock d'officine — PHARMA-003 (#7800). deny-by-default : lecture
 * pour tout employé du tenant (le comptoir consulte les niveaux), les
 * ajustements d'inventaire sont réservés au manager.
 */
class PharmacyStockPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, PharmacyStockMovement $movement): bool
    {
        return $movement->company_id === (string) $actor->company_id;
    }
}
