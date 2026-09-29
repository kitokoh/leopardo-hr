<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Infrastructure\Services\EduGradeService;

/**
 * Cas d'usage : correction versionnée d'une note (EDU-010, issue #5826, EDU-007).
 *
 * Consommé par `POST .../edu-manager/grades/{grade}/correct`
 * (EduAssessmentController::correctGrade). Chaque correction incrémente la
 * version et journalise l'ancienne valeur ; la règle métier reste dans
 * EduGradeService (Infrastructure).
 */
class CorrectEduGradeAction
{
    public function __construct(
        private readonly EduGradeService $grades,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, EduGrade $grade, array $validated): EduGrade
    {
        return $this->grades->correct($actor, $grade, $validated);
    }
}
