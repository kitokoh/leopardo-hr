<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrder;

/**
 * RBAC des commandes d'achat d'officine — PHARMA-004 (#7801).
 * deny-by-default : écriture et transitions (passage, annulation,
 * réception) réservées aux managers, lecture pour tout employé du tenant.
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
