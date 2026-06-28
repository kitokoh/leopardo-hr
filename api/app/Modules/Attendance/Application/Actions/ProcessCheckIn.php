<?php

namespace App\Modules\Attendance\Application\Actions;

use App\Modules\Attendance\Application\DTOs\CheckInDTO;
use App\Modules\Attendance\Domain\Exceptions\AlreadyCheckedInException;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendanceService;

class ProcessCheckIn
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * @throws AlreadyCheckedInException
     */
    public function handle(CheckInDTO $dto): AttendanceLog
    {
        return $this->attendanceService->checkIn($dto);
    }
}
