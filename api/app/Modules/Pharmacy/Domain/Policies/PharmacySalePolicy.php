<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacySale;

/**
 * RBAC des ventes comptoir — PHARMA-005 (#7802). deny-by-default : tout
 * employé du tenant peut encaisser (le comptoir est tenu par les
 * préparateurs) et consulter ; l'ANNULATION (void) est réservée aux
 * managers (acte comptable sensible).
 */
class PharmacySalePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, PharmacySale $sale): bool
    {
        return $sale->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return true;
    }

    public function void(Employee $actor, PharmacySale $sale): bool
    {
        return $actor->isManager() && $sale->company_id === (string) $actor->company_id;
    }
}
