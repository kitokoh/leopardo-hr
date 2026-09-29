<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Modules\EduManager\Domain\Models\EduStudent;

/**
 * Cas d'usage : archivage d'un élève (EDU-010, issue #5826).
 *
 * Consommé par `DELETE .../edu-manager/students/{student}`
 * (EduStudentController::destroy). Suppression physique interdite pour les
 * élèves (RGPD, données de mineurs) : le statut passe à `archived`.
 */
class ArchiveEduStudentAction
{
    public function execute(EduStudent $student): EduStudent
    {
        $student->update(['status' => EduStudent::STATUS_ARCHIVED]);

        return $student;
    }
}
