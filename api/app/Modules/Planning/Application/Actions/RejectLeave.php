<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Modules\Planning\Domain\Exceptions\AbsenceNotPendingException;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Infrastructure\Services\AbsenceService;

class RejectLeave
{
    public function __construct(
        private readonly AbsenceService $absenceService,
    ) {}

    /**
     * @throws AbsenceNotPendingException
     */
    public function handle(string $absenceId, string $rejectedById, ?string $reason = null): Absence
    {
        $absence = Absence::query()->findOrFail($absenceId);

        if ($absence->status !== 'pending') {
            throw new AbsenceNotPendingException($absenceId);
        }

        return $this->absenceService->reject($absence, $rejectedById, $reason);
    }
}
