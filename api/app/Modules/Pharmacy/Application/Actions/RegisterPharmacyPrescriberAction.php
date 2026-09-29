<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescriber;

/**
 * Cas d'usage : enregistrement d'un prescripteur — PHARMA-006 (#7803).
 *
 * Consommé par `POST /api/v1/pharmacy/prescribers`
 * (PharmacyPrescriberController::store). La Policy `create` (écriture
 * manager) reste au niveau interface ; l'Action porte le cas d'usage
 * nommable, y compris le défaut métier (statut actif).
 */
class RegisterPharmacyPrescriberAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Employee $actor, array $payload): PharmacyPrescriber
    {
        /** @var PharmacyPrescriber $prescriber */
        $prescriber = PharmacyPrescriber::query()->create(array_merge(
            ['status' => 'active'],
            $payload,
            ['company_id' => $actor->company_id],
        ));

        return $prescriber;
    }
}
