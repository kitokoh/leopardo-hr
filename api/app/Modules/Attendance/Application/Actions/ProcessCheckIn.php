<?php

namespace App\Modules\Attendance\Application\Actions;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Modules\Attendance\Application\DTOs\CheckInDTO;
use App\Modules\Attendance\Infrastructure\Services\AttendanceService;

class ProcessCheckIn
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    public function handle(Employee $employee, CheckInDTO $dto): AttendanceLog
    {
        return $this->attendanceService->checkIn($employee, $dto);
    }
}
