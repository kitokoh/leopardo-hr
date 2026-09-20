<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;

/**
 * RBAC du référentiel produits — PHARMA-002 (#7799). deny-by-default :
 * écriture manager, lecture pour tout employé du tenant (le comptoir doit
 * pouvoir consulter le catalogue).
 */
class PharmacyProductPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, PharmacyProduct $product): bool
    {
        return $product->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, PharmacyProduct $product): bool
    {
        return $actor->isManager() && $product->company_id === (string) $actor->company_id;
    }
}
