<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelDelivery;
use App\Modules\FuelStation\Domain\Models\FuelStockMovement;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;

/**
 * RBAC des stocks, livraisons et rapprochements FuelStation (FUEL-009,
 * #5803).
 *
 * - Manager : gestion complète (livraisons, ajustements, rapprochements).
 * - Employé (pompiste) : pas d'accès direct à la gestion des stocks — la
 *   lecture des niveaux passe par l'interface manager (deny-by-default).
 */
class FuelStockPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, FuelStockMovement|FuelDelivery|FuelStockReconciliation $resource): bool
    {
        return $actor->isManager();
    }

    public function createDelivery(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function verifyDelivery(Employee $actor, FuelDelivery $delivery): bool
    {
        return $actor->isManager();
    }

    public function adjust(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function reconcile(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
