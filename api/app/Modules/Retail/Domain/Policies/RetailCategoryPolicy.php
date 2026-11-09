<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailCategory;
use App\Modules\Retail\Domain\Permissions\RetailPermissions;

/**
 * RBAC des categories du module Retail (BC-17 RETAIL, #7672).
 *
 * #9207 — permission `retail.catalog` reellement appliquee (direction, ou
 * niveau `operate` sur au moins une boutique assignee) ; lecture ouverte aux
 * membres du tenant (scope `company_id` verifie).
 * deny-by-default : aucun role = refus (fail-closed, pattern Catalog #6880).
 */
class RetailCategoryPolicy
{
    public function __construct(private readonly RetailPermissions $permissions = new RetailPermissions) {}

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RetailCategory $category): bool
    {
        return $category->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->permissions->canManageCatalog($actor);
    }

    public function update(Employee $actor, RetailCategory $category): bool
    {
        return $category->company_id === (string) $actor->company_id
            && $this->permissions->canManageCatalog($actor);
    }

    public function delete(Employee $actor, RetailCategory $category): bool
    {
        return $this->update($actor, $category);
    }
}
