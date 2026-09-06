<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Catalog\Domain\Models\CatalogCategory;

/**
 * RBAC des catégories du catalogue B2B (BC-28 CATALOG, #6880).
 *
 * Gestion réservée au responsable du tenant (sous-rôles `principal`/`rh`) ;
 * lecture ouverte aux membres du tenant (scope `company_id` vérifié).
 * deny-by-default : aucun rôle = refus (fail-closed).
 */
class CatalogCategoryPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, CatalogCategory $category): bool
    {
        return $category->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, CatalogCategory $category): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $category->company_id === (string) $actor->company_id;
    }

    public function delete(Employee $actor, CatalogCategory $category): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $category->company_id === (string) $actor->company_id;
    }
}
