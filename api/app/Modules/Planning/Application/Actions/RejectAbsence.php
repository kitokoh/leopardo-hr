<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Infrastructure\Services\AbsenceService;

/**
 * Cas d'usage : rejet d'une demande d'absence par un manager (motif requis).
 *
 * Consommé par `POST|PUT /api/v1/absences/{absence}/reject`
 * (AbsenceController::reject, module façade Absence).
 *
 * @throws \App\Modules\Planning\Domain\Exceptions\AbsenceNotPendingException
 */
class RejectAbsence
{
    public function __construct(
        private readonly AbsenceService $absences,
    ) {}

    public function execute(Absence $absence, string $reason): Absence
    {
        return $this->absences->reject($absence, $reason);
    }
}
