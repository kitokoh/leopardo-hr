<?php

namespace App\Modules\Attendance\Application\Actions;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Modules\Attendance\Infrastructure\Services\AttendanceService;

class ProcessCheckOut
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    public function handle(Employee $employee): AttendanceLog
    {
        return $this->attendanceService->checkOut($employee);
    }
}
