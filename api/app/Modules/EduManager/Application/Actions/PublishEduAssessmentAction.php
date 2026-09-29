<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Modules\EduManager\Domain\Models\EduAssessment;

/**
 * Cas d'usage : publication d'une évaluation (EDU-010, issue #5826, EDU-007).
 *
 * Consommé par `POST .../edu-manager/assessments/{assessment}/publish`
 * (EduAssessmentController::publish). Idempotent : une évaluation déjà
 * publiée conserve son `published_at` d'origine.
 */
class PublishEduAssessmentAction
{
    public function execute(EduAssessment $assessment): EduAssessment
    {
        if (! $assessment->isPublished()) {
            $assessment->update(['published_at' => now()]);
        }

        return $assessment->refresh();
    }
}
