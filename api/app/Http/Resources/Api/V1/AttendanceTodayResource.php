<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\EstimationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceLog
 */
class AttendanceTodayResource extends JsonResource
{
    private ?AttendanceLog $log;

    private string $timezone;

    /**
     * @param  Employee  $resource
     */
    public function __construct($resource, ?AttendanceLog $log = null, ?string $timezone = null)
    {
        parent::__construct($resource);
        $this->log = $log;
        $this->timezone = $timezone ?? currentCompany()->timezone;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Employee $employee */
        $employee = $this->resource;

        $estimationService = app(EstimationService::class);
        $summary = $estimationService->dailySummaryFromLog($employee, $this->log, $this->log?->date?->toDateString());

        return [
            'id' => $this->log?->id,
            'employee_id' => $employee->id,
            'matricule' => $employee->matricule,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'checked_in' => (bool) $this->log?->check_in,
            'check_in_time' => $this->log?->check_in?->setTimezone($this->timezone)->format('H:i'),
            'check_out_time' => $this->log?->check_out?->setTimezone($this->timezone)->format('H:i'),
            'hours_worked' => (float) ($this->log?->hours_worked ?? 0.00),
            'overtime_hours' => (float) ($this->log?->overtime_hours ?? 0.00),
            'status' => $this->log?->status ?? 'absent',
            'late_minutes' => $this->log?->late_minutes,
            'base_gain' => (float) $summary['base_gain'],
            'overtime_gain' => (float) $summary['overtime_gain'],
            'total_estimated' => (float) $summary['total_estimated'],
            'currency' => $summary['currency'],
        ];
    }
}
