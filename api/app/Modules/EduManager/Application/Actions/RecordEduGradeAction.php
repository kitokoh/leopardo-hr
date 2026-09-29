<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Infrastructure\Services\EduGradeService;

/**
 * Cas d'usage : saisie d'une note sur une évaluation (EDU-010, issue #5826, EDU-007).
 *
 * Consommé par `POST .../edu-manager/assessments/{assessment}/grades`
 * (EduAssessmentController::grade). Notes bornées [0, max_score] ; la règle
 * métier reste dans EduGradeService (Infrastructure).
 */
class RecordEduGradeAction
{
    public function __construct(
        private readonly EduGradeService $grades,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, EduAssessment $assessment, array $validated): EduGrade
    {
        return $this->grades->grade($actor, $assessment, $validated);
    }
}
