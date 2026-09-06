<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Infrastructure\Services\AbsenceService;

/**
 * Cas d'usage : annulation d'une demande d'absence en attente par son
 * auteur (#2329 — les jours en attente sont libérés).
 *
 * Consommé par `DELETE /api/v1/absences/{absence}` (AbsenceController::destroy,
 * module façade Absence).
 *
 * @throws \App\Modules\Planning\Domain\Exceptions\AbsenceNotPendingException
 */
class CancelAbsence
{
    public function __construct(
        private readonly AbsenceService $absences,
    ) {}

    public function execute(Absence $absence): Absence
    {
        return $this->absences->cancel($absence);
    }
}
