<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Infrastructure\Services\EduAdmissionService;

/**
 * Cas d'usage : conversion d'une admission en élève (EDU-010, issue #5826, EDU-004).
 *
 * Consommé par `POST .../edu-manager/admissions/{admission}/convert`
 * (EduAdmissionController::convert). Conversion idempotente avec consentement
 * obligatoire (422 `EDU_CONSENT_REQUIRED` sinon) ; la règle métier reste dans
 * EduAdmissionService (Infrastructure).
 */
class ConvertEduAdmissionToStudentAction
{
    public function __construct(
        private readonly EduAdmissionService $admissions,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, EduAdmission $admission, array $validated = []): EduStudent
    {
        return $this->admissions->convertToStudent($actor, $admission, $validated);
    }
}
