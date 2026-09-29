<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAssessment;

/**
 * Cas d'usage : création d'une évaluation (EDU-010, issue #5826, EDU-007).
 *
 * Consommé par `POST .../edu-manager/assessments` (EduAssessmentController::store).
 * Le tenant vient de la session de l'acteur et l'auteur est tracé
 * (`created_by`).
 */
class CreateEduAssessmentAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, array $validated): EduAssessment
    {
        /** @var EduAssessment $assessment */
        $assessment = EduAssessment::query()->create(array_merge($validated, [
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]));

        return $assessment;
    }
}
