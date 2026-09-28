<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacySupplier;

/**
 * Cas d'usage : enregistrement d'un fournisseur d'officine — PHARMA-004
 * (#7801).
 *
 * Consommé par `POST /api/v1/pharmacy/suppliers`
 * (PharmacySupplierController::store). La Policy `create` (écriture
 * manager) reste au niveau interface ; l'Action porte le cas d'usage
 * nommable, y compris les défauts métier (grossiste, statut actif).
 */
class RegisterPharmacySupplierAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Employee $actor, array $payload): PharmacySupplier
    {
        /** @var PharmacySupplier $supplier */
        $supplier = PharmacySupplier::query()->create(array_merge(
            ['type' => 'wholesaler', 'status' => 'active'],
            $payload,
            ['company_id' => $actor->company_id],
        ));

        return $supplier;
    }
}
