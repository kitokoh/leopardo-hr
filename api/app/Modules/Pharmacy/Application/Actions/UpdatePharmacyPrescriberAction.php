<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Modules\Pharmacy\Domain\Models\PharmacyPrescriber;

/**
 * Cas d'usage : mise à jour d'un prescripteur (dont archivage via
 * `status`) — PHARMA-006 (#7803).
 *
 * Consommé par `PUT /api/v1/pharmacy/prescribers/{prescriber}`
 * (PharmacyPrescriberController::update). Le contrôle tenant (404) et la
 * Policy `update` restent au niveau interface ; l'Action porte le cas
 * d'usage nommable.
 */
class UpdatePharmacyPrescriberAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(PharmacyPrescriber $prescriber, array $payload): PharmacyPrescriber
    {
        $prescriber->update($payload);

        return $prescriber;
    }
}
