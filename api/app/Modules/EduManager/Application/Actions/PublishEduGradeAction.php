<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Infrastructure\Services\EduGradeService;

/**
 * Cas d'usage : publication d'une note (EDU-010, issue #5826, EDU-007).
 *
 * Consommé par `POST .../edu-manager/grades/{grade}/publish`
 * (EduAssessmentController::publishGrade). La règle métier reste dans
 * EduGradeService (Infrastructure).
 */
class PublishEduGradeAction
{
    public function __construct(
        private readonly EduGradeService $grades,
    ) {}

    public function execute(Employee $actor, EduGrade $grade): EduGrade
    {
        return $this->grades->publish($actor, $grade);
    }
}
