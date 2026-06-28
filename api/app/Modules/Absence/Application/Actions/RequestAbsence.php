<?php

declare(strict_types=1);

namespace App\Modules\Absence\Application\Actions;

use App\Modules\Absence\Application\DTOs\RequestAbsenceDTO;
use App\Modules\Absence\Domain\Exceptions\AbsenceDateConflictException;
use App\Modules\Absence\Domain\Exceptions\InsufficientLeaveBalanceException;
use App\Modules\Absence\Domain\Models\Absence;
use App\Modules\Absence\Infrastructure\Services\AbsenceService;

class RequestAbsence
{
    public function __construct(
        private readonly AbsenceService $absenceService,
    ) {}

    /**
     * @throws AbsenceDateConflictException
     * @throws InsufficientLeaveBalanceException
     */
    public function handle(RequestAbsenceDTO $dto): Absence
    {
        return $this->absenceService->request($dto);
    }
}
