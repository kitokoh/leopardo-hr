<?php

declare(strict_types=1);

namespace App\Modules\Absence\Application\Actions;

use App\Modules\Absence\Domain\Exceptions\AbsenceNotPendingException;
use App\Modules\Absence\Domain\Models\Absence;
use App\Modules\Absence\Infrastructure\Services\AbsenceService;

class RejectAbsence
{
    public function __construct(
        private readonly AbsenceService $absenceService,
    ) {}

    /**
     * @throws AbsenceNotPendingException
     */
    public function handle(Absence $absence, int $rejectedBy, string $comment): Absence
    {
        return $this->absenceService->reject($absence, $rejectedBy, $comment);
    }
}
