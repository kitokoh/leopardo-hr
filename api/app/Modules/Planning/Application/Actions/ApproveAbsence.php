<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Infrastructure\Services\AbsenceService;

/**
 * Cas d'usage : approbation d'une demande d'absence par un manager (verrou
 * ligne + revalidation du solde, #2666).
 *
 * Consommé par `POST|PUT /api/v1/absences/{absence}/approve`
 * (AbsenceController::approve, module façade Absence).
 */
class ApproveAbsence
{
    public function __construct(
        private readonly AbsenceService $absences,
    ) {}

    public function execute(Absence $absence, Employee $approver): Absence
    {
        return $this->absences->approve($absence, $approver);
    }
}
