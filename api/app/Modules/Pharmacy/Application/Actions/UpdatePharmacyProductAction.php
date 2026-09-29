<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;

/**
 * Cas d'usage : mise à jour d'une fiche produit d'officine — PHARMA-002
 * (#7799).
 *
 * Consommé par `PUT /api/v1/pharmacy/products/{product}`
 * (PharmacyProductController::update). Le contrôle tenant (404) et la
 * Policy `update` restent au niveau interface ; l'Action porte le cas
 * d'usage nommable.
 */
class UpdatePharmacyProductAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(PharmacyProduct $product, array $payload): PharmacyProduct
    {
        $product->update($payload);

        return $product;
    }
}
