<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationReport;
use App\Modules\FuelStation\Domain\Models\FuelStockDelivery;
use App\Modules\FuelStation\Domain\Models\FuelTankStockLevel;

/**
 * RBAC stocks/rapprochement FuelStation (FUEL-009, #5803).
 *
 * Deny-by-default : toutes les opérations de stock (niveaux, livraisons,
 * rapprochement, revue) sont réservées au manager — un pompiste n'écrit
 * jamais le stock. Les consultations sont manager-only également (le
 * pompiste ne voit que ses ventes via /me/*, pas les niveaux).
 */
class FuelStockPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelStockDelivery|FuelTankStockLevel|FuelReconciliationReport $resource): bool
    {
        return $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function receive(Employee $actor, FuelStockDelivery $delivery): bool
    {
        return $actor->isManager();
    }

    public function review(Employee $actor, FuelReconciliationReport $report): bool
    {
        return $actor->isManager();
    }
}
