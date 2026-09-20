<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescriber;

/**
 * RBAC prescripteurs — PHARMA-006 (#7803). Écriture manager, lecture pour
 * tout employé du tenant (le comptoir rattache l'ordonnance).
 */
class PharmacyPrescriberPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, PharmacyPrescriber $prescriber): bool
    {
        return $prescriber->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, PharmacyPrescriber $prescriber): bool
    {
        return $actor->isManager() && $prescriber->company_id === (string) $actor->company_id;
    }
}
