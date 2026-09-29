<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescription;

/**
 * Cas d'usage : saisie d'une ordonnance au comptoir (délivrance) —
 * PHARMA-006 (#7803). PII santé strictement tenant-scopée : le tenant vient
 * de l'acteur authentifié (session), jamais de la charge utile.
 *
 * Consommé par `POST /api/v1/pharmacy/prescriptions`
 * (PharmacyPrescriptionController::store). La résolution tenant du
 * prescripteur (404 si hors tenant) et la Policy `create` restent au
 * niveau interface ; l'Action porte le cas d'usage nommable.
 */
class RegisterPharmacyPrescriptionAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Employee $actor, array $payload): PharmacyPrescription
    {
        /** @var PharmacyPrescription $prescription */
        $prescription = PharmacyPrescription::query()->create(array_merge(
            $payload,
            ['company_id' => $actor->company_id],
        ));

        return $prescription;
    }
}
