<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;

/**
 * RBAC du stock d'officine — PHARMA-003 (#7800). deny-by-default : lecture
 * (niveaux, lots, mouvements, alertes) pour tout employé du tenant (le
 * comptoir doit voir les péremptions), ajustements d'inventaire réservés
 * aux managers.
 */
class PharmacyStockPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, PharmacyBatch $batch): bool
    {
        return $batch->company_id === (string) $actor->company_id;
    }

    public function adjust(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
