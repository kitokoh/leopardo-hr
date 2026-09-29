<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Models\HealthPatient;

/**
 * Cas d'usage « archiver un patient » — HC-003 (#7787, BC-31).
 *
 * Extrait de `HealthPatientController::archive` (BOS-024b, #8213) :
 * archivage logique — les patients ne sont JAMAIS supprimés (spec §3).
 */
final class ArchiveHealthPatientAction
{
    public function execute(HealthPatient $patient): HealthPatient
    {
        $patient->update(['status' => HealthPatient::STATUS_ARCHIVED]);

        return $patient->refresh();
    }
}
