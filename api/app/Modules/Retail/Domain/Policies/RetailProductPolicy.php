<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailProduct;

/**
 * RBAC des produits du module Retail (BC-17 RETAIL, #7672).
 *
 * Gestion (CRUD, publication) réservée au responsable du tenant
 * (sous-rôles `principal`/`rh`) ; lecture ouverte aux membres du tenant
 * (scope `company_id` vérifié). deny-by-default : aucun rôle = refus
 * (fail-closed, pattern Catalog #6880).
 */
class RetailProductPolicy
{
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
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, RetailProduct $product): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $product->company_id === (string) $actor->company_id;
    }

    public function delete(Employee $actor, RetailProduct $product): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $product->company_id === (string) $actor->company_id;
    }

    /**
     * Publication / dépublication — même portée que l'édition.
     */
    public function publish(Employee $actor, RetailProduct $product): bool
    {
        return $this->update($actor, $product);
    }
}
