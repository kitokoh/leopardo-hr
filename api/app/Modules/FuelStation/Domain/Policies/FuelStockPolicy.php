<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use App\Modules\FuelStation\Domain\Models\FuelTankDelivery;

/**
 * RBAC des stocks FuelStation (FUEL-009, #5803).
 *
 * - Manager (role=manager) : enregistrement des livraisons et pilotage des
 *   rapprochements (deny-by-default pour tout autre rôle).
 * - Employé (pompiste) : aucun accès d'administration — il ne lit ni ne
 *   modifie les stocks via ces endpoints (lecture via le dashboard manager).
 *
 * Issue #7600 (R3) — la garde de rôle est désormais CROISÉE avec l'accès
 * ressource-scopé par station (`ChecksResourceScopedAccess`) : dès qu'une
 * entreprise assigne le type `fuel_station`, un manager ne peut plus opérer
 * que sur les stations qui lui sont attribuées (progression : tant qu'aucune
 * assignation n'existe, le comportement historique est conservé).
 */
class FuelStockPolicy
{
    use ChecksResourceScopedAccess;

    public function createDelivery(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function viewStocks(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function runReconciliation(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function viewAnyReconciliation(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    public function viewReconciliation(Employee $actor, FuelReconciliationRun $run): bool
    {
        return $run->company_id === (string) $actor->company_id
            && $this->canManageScopedResource($actor, 'fuel_station', $run->station_id, $actor->isManager());
    }

    public function viewDelivery(Employee $actor, FuelTankDelivery $delivery): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }

    /**
     * Issue #7398 — vérification d'une livraison déclarée
     * (`POST /fuel-station/deliveries/{delivery}/verify`) : acte de contrôle
     * réservé au manager, comme l'enregistrement de la livraison.
     */
    public function verifyDelivery(Employee $actor, FuelTankDelivery $delivery): bool
    {
        return $this->canManageScopedResource($actor, 'fuel_station', null, $actor->isManager());
    }
}
