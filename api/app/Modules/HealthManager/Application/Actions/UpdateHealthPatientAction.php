<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Models\HealthPatient;

/**
 * Cas d'usage « mettre à jour un patient » — HC-003 (#7787, BC-31).
 *
 * Extrait de `HealthPatientController::update` (BOS-024b, #8213) : même
 * mapping clairs API → colonnes chiffrées qu'à la création (source
 * unique : `RegisterHealthPatientAction::mapEncryptedInputs()`).
 */
final class UpdateHealthPatientAction
{
    /**
     * @param  array<string, mixed>  $validated  Payload validé (UpdateHealthPatientRequest).
     */
    public function execute(HealthPatient $patient, array $validated): HealthPatient
    {
        $patient->update(RegisterHealthPatientAction::mapEncryptedInputs($validated));

        return $patient->refresh();
    }
}
