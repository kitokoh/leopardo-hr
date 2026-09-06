<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Models\Applicant;

/**
 * Cas d'usage : suppression d'une candidature.
 */
class DeleteApplicantAction
{
    public function execute(Applicant $applicant): void
    {
        $applicant->delete();
    }
}
