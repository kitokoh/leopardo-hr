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

    public function viewStocks(Employee $actor): bool
    public function view(Employee $actor, FuelStockMovement|FuelDelivery|FuelStockReconciliation $resource): bool
    {
        return $actor->isManager();
    }

    public function runReconciliation(Employee $actor): bool
    public function createDelivery(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function viewAnyReconciliation(Employee $actor): bool
    public function verifyDelivery(Employee $actor, FuelDelivery $delivery): bool
    {
        return $actor->isManager();
    }

    public function viewReconciliation(Employee $actor, FuelReconciliationRun $run): bool
    public function adjust(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function viewDelivery(Employee $actor, FuelTankDelivery $delivery): bool
    public function reconcile(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
