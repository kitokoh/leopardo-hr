<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Infrastructure\Services\AbsenceService;
use Illuminate\Http\UploadedFile;

/**
 * Cas d'usage : création d'une demande d'absence (congés) par un employé.
 *
 * Orchestration pure — la politique métier (solde, jours ouvrés pays, règles
 * légales) reste dans AbsenceService (Infrastructure). Consommé par
 * `POST /api/v1/absences` (AbsenceController::store, module façade Absence).
 *
 * @throws \App\Modules\Planning\Domain\Exceptions\InsufficientLeaveBalanceException
 * @throws \App\Modules\Planning\Domain\Exceptions\AbsenceDateConflictException
 * @throws \App\Modules\Planning\Domain\Exceptions\UnsupportedLeaveCountryException
 */
class CreateAbsence
{
    public function __construct(
        private readonly AbsenceService $absences,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Employee $employee, array $data, ?UploadedFile $proof = null): Absence
    {
        return $this->absences->create($employee, $data, $proof);
    }
}
