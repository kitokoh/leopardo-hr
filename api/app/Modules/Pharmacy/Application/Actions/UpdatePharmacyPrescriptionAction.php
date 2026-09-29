<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Modules\Pharmacy\Domain\Models\PharmacyPrescription;

/**
 * Cas d'usage : correction d'une ordonnance saisie — PHARMA-006 (#7803).
 *
 * Consommé par `PUT /api/v1/pharmacy/prescriptions/{prescription}`
 * (PharmacyPrescriptionController::update). Le contrôle tenant de la
 * ressource (404), du prescripteur éventuel et la Policy `update` restent
 * au niveau interface ; l'Action porte le cas d'usage nommable.
 */
class UpdatePharmacyPrescriptionAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(PharmacyPrescription $prescription, array $payload): PharmacyPrescription
    {
        $prescription->update($payload);

        return $prescription;
    }
}
