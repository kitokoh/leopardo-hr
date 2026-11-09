<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Permissions\RetailPermissions;

/**
 * RBAC des produits du module Retail (BC-17 RETAIL, #7672).
 *
 * #9207 — permission `retail.catalog` reellement appliquee : gerer produits,
 * prix et publication est un geste de VENDEUR (direction, ou niveau `operate`
 * sur au moins une boutique assignee) — un vendeur doit pouvoir corriger un
 * prix ou creer un produit en rayon. Lecture ouverte aux membres du tenant
 * (scope `company_id` verifie). deny-by-default : aucun role = refus.
 */
class RetailProductPolicy
{
    public function __construct(private readonly RetailPermissions $permissions = new RetailPermissions) {}

    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, RetailProduct $product): bool
    {
        return $product->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->permissions->canManageCatalog($actor);
    }

    public function update(Employee $actor, RetailProduct $product): bool
    {
        return $product->company_id === (string) $actor->company_id
            && $this->permissions->canManageCatalog($actor);
    }

    public function delete(Employee $actor, RetailProduct $product): bool
    {
        return $this->update($actor, $product);
    }

    /**
     * Publication / depublication — meme portee que l'edition.
     */
    public function publish(Employee $actor, RetailProduct $product): bool
    {
        return $this->update($actor, $product);
    }
}
