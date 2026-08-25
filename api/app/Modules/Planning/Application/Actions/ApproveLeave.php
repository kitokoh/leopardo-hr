<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Exceptions\AbsenceNotPendingException;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Infrastructure\Services\AbsenceService;

class ApproveLeave
{
    public function __construct(
        private readonly AbsenceService $absenceService,
    ) {}

    /**
     * @throws AbsenceNotPendingException
     */
    public function handle(string $absenceId, string $approvedById): Absence
    {
        $absence = Absence::query()->findOrFail($absenceId);

        if ($absence->status !== 'pending') {
            throw new AbsenceNotPendingException($absenceId);
        }

        $approver = Employee::query()->findOrFail($approvedById);

        return $this->absenceService->approve($absence, $approver);
    }
}
