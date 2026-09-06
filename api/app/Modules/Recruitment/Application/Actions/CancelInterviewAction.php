<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Models\Interview;

/**
 * Cas d'usage : annulation d'un entretien (statut « cancelled », suppression logique).
 */
class CancelInterviewAction
{
    public function execute(Interview $interview): Interview
    {
        $interview->update(['status' => 'cancelled']);

        return $interview->fresh();
    }
}
