<?php

namespace App\Modules\Attendance\Application\Actions;

use App\Modules\Attendance\Domain\Exceptions\MissingCheckInException;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendanceService;

class ProcessCheckOut
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * @throws MissingCheckInException
     */
    public function handle(string $employeeId): AttendanceLog
    {
        return $this->attendanceService->checkOut($employeeId);
    }
}
