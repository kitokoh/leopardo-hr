<?php

namespace App\Modules\Planning\Application\Actions;

use App\Exceptions\AbsenceNotPendingException;
use App\Models\Absence;
use App\Modules\Planning\Infrastructure\Services\AbsenceService;

class RejectLeave
{
    public function __construct(
        private readonly AbsenceService $absenceService,
    ) {}

    /**
     * @throws AbsenceNotPendingException
     */
    public function handle(string $absenceId, string $reason): Absence
    {
        /** @var Absence $absence */
        $absence = Absence::query()->findOrFail($absenceId);

        return $this->absenceService->reject($absence, $reason);
    }
}
