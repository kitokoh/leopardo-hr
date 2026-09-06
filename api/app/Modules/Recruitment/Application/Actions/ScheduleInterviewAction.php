<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\Interview;

/**
 * Cas d'usage : planification d'un entretien pour une candidature.
 */
class ScheduleInterviewAction
{
    /**
     * @param  array<string, mixed>  $data  champs validés (sans company_id/applicant_id)
     */
    public function execute(int $companyId, Applicant $applicant, array $data): Interview
    {
        return Interview::create([
            ...$data,
            'company_id' => $companyId,
            'applicant_id' => $applicant->id,
        ]);
    }
}
