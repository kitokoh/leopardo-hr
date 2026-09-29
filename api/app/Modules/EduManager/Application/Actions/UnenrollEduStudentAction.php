<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Modules\EduManager\Domain\Models\EduClassEnrollment;

/**
 * Cas d'usage : désinscription d'un élève d'une classe (EDU-011, issue #5827).
 *
 * Consommé par `DELETE .../edu-manager/enrollments/{enrollment}`
 * (EduClassEnrollmentController::destroy). Désinscription douce : le statut
 * passe à `inactive`, l'historique est conservé.
 */
class UnenrollEduStudentAction
{
    public function execute(EduClassEnrollment $enrollment): EduClassEnrollment
    {
        $enrollment->update(['status' => EduClassEnrollment::STATUS_INACTIVE]);

        return $enrollment;
    }
}
