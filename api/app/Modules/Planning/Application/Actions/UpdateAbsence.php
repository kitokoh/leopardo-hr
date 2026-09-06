<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Infrastructure\Services\AbsenceService;

/**
 * Cas d'usage : modification d'une demande d'absence en attente (dates/raison,
 * #4933 — une demande approuvée/rejetée est un état terminal).
 *
 * Consommé par `PUT /api/v1/absences/{absence}` (AbsenceController::update,
 * module façade Absence).
 *
 * @throws \App\Modules\Planning\Domain\Exceptions\AbsenceNotPendingException
 */
class UpdateAbsence
{
    public function __construct(
        private readonly AbsenceService $absences,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Absence $absence, array $data): Absence
    {
        return $this->absences->update($absence, $data);
    }
}
