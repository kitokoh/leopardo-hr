<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrder;

/**
 * RBAC commandes d'achat — PHARMA-004 (#7801). Écriture et transitions
 * manager, lecture pour tout employé du tenant.
 */
class PharmacyPurchaseOrderPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, PharmacyPurchaseOrder $order): bool
    {
        return $order->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, PharmacyPurchaseOrder $order): bool
    {
        return $actor->isManager() && $order->company_id === (string) $actor->company_id;
    }
}
