<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Models\Interview;

/**
 * Cas d'usage : dépôt du feedback d'un entretien → statut « completed ».
 */
class SubmitInterviewFeedbackAction
{
    /**
     * @param  array<string, mixed>  $data  feedback + rating éventuel
     */
    public function execute(Interview $interview, array $data): Interview
    {
        $interview->update([
            ...$data,
            'status' => 'completed',
        ]);

        return $interview->fresh() ?? $interview;
    }
}
