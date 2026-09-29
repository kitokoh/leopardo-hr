<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;

/**
 * Cas d'usage : création d'un produit au référentiel d'officine —
 * PHARMA-002 (#7799).
 *
 * Consommé par `POST /api/v1/pharmacy/products`
 * (PharmacyProductController::store). La Policy `create` (écriture manager)
 * reste au niveau interface ; l'Action porte le cas d'usage nommable, y
 * compris les défauts métier du référentiel (catégorie, unité, drapeaux,
 * prix, statut actif).
 */
class CreatePharmacyProductAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Employee $actor, array $payload): PharmacyProduct
    {
        /** @var PharmacyProduct $product */
        $product = PharmacyProduct::query()->create(array_merge(
            [
                'category' => 'medicament',
                'unit' => 'unite',
                'prescription_required' => false,
                'is_controlled' => false,
                'purchase_price' => 0,
                'sale_price' => 0,
                'tax_rate' => 0,
                'min_stock_level' => 0,
                'status' => 'active',
            ],
            $payload,
            ['company_id' => $actor->company_id],
        ));

        return $product;
    }
}
