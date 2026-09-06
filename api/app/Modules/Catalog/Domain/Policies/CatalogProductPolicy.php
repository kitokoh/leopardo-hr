<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Catalog\Domain\Models\CatalogProduct;

/**
 * RBAC des produits du catalogue B2B (BC-28 CATALOG, #6880).
 *
 * Gestion (CRUD, publication) réservée au responsable du tenant
 * (sous-rôles `principal`/`rh`) ; lecture ouverte aux membres du tenant
 * (scope `company_id` vérifié). deny-by-default : aucun rôle = refus.
 */
class CatalogProductPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, CatalogProduct $product): bool
    {
        return $product->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, CatalogProduct $product): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $product->company_id === (string) $actor->company_id;
    }

    public function delete(Employee $actor, CatalogProduct $product): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $product->company_id === (string) $actor->company_id;
    }

    /**
     * Publication / dépublication — même portée que l'édition.
     */
    public function publish(Employee $actor, CatalogProduct $product): bool
    {
        return $this->update($actor, $product);
    }
}
