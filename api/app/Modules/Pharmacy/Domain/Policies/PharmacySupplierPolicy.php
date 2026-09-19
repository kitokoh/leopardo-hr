<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacySupplier;

/**
 * RBAC des fournisseurs d'officine — PHARMA-004 (#7801). deny-by-default :
 * écriture manager, lecture pour tout employé du tenant.
 */
class PharmacySupplierPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, PharmacySupplier $supplier): bool
    {
        return $supplier->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, PharmacySupplier $supplier): bool
    {
        return $actor->isManager() && $supplier->company_id === (string) $actor->company_id;
    }
}
