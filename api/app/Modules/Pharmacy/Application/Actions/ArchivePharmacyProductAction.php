<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;

/**
 * Cas d'usage : archivage d'un produit d'officine — PHARMA-002 (#7799).
 * Jamais de suppression : le produit peut être référencé par des lots,
 * ventes et commandes (traçabilité réglementaire).
 *
 * Consommé par `POST /api/v1/pharmacy/products/{product}/archive`
 * (PharmacyProductController::archive). Le contrôle tenant (404) et la
 * Policy `update` restent au niveau interface ; l'Action porte le cas
 * d'usage nommable.
 */
class ArchivePharmacyProductAction
{
    public function execute(PharmacyProduct $product): PharmacyProduct
    {
        $product->update(['status' => 'archived']);

        return $product;
    }
}
