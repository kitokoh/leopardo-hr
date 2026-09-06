<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Models\Applicant;

/**
 * Cas d'usage : mise à jour d'une candidature (statut, évaluation, notes).
 */
class UpdateApplicantAction
{
    /**
     * @param  array<string, mixed>  $data  champs validés
     */
    public function execute(Applicant $applicant, array $data): Applicant
    {
        $applicant->update($data);

        return $applicant->fresh();
    }
}
