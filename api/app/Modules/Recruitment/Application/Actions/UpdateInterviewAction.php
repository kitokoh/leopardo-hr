<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Models\Interview;

/**
 * Cas d'usage : mise à jour d'un entretien (statut, feedback, évaluation).
 */
class UpdateInterviewAction
{
    /**
     * @param  array<string, mixed>  $data  champs validés
     */
    public function execute(Interview $interview, array $data): Interview
    {
        $interview->update($data);

        return $interview->fresh();
    }
}
