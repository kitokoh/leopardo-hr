<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Modules\Pharmacy\Domain\Models\PharmacySupplier;

/**
 * Cas d'usage : mise à jour d'un fournisseur d'officine — PHARMA-004
 * (#7801).
 *
 * Consommé par `PUT /api/v1/pharmacy/suppliers/{supplier}`
 * (PharmacySupplierController::update). Le contrôle tenant (404) et la
 * Policy `update` restent au niveau interface ; l'Action porte le cas
 * d'usage nommable.
 */
class UpdatePharmacySupplierAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(PharmacySupplier $supplier, array $payload): PharmacySupplier
    {
        $supplier->update($payload);

        return $supplier;
    }
}
