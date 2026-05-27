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

    /** @var array<string, mixed>|null */
    private ?array $summary;

    /**
     * @param  Employee  $resource
     * @param  array<string, mixed>|null  $summary
     */
    public function __construct($resource, ?AttendanceLog $log = null, ?string $timezone = null, ?array $summary = null)
    {
        parent::__construct($resource);
        $this->log = $log;
        $this->timezone = $timezone ?? currentCompany()->timezone;
        $this->summary = $summary;
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
        $summary = $this->summary
            ?? $estimationService->dailySummary($employee, $this->log?->date?->toDateString());

        return [
            'id' => $this->log?->id,
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'matricule' => $employee->matricule,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'checked_in' => (bool) $this->log?->check_in,
            'session_number' => (int) ($this->log?->session_number ?? 0),
            'check_in_time' => $this->log?->check_in?->setTimezone($this->timezone)->format('H:i'),
            'check_out_time' => $this->log?->check_out?->setTimezone($this->timezone)->format('H:i'),
            'sessions_count' => (int) ($summary['sessions_count'] ?? 0),
            'work_type' => $this->log?->work_type ?? 'normal',
            'hours_worked' => (float) ($summary['hours_worked'] ?? 0.00),
            'overtime_hours' => (float) ($summary['overtime_hours'] ?? 0.00),
            'status' => $summary['status'] ?? ($this->log?->status ?? 'absent'),
            'late_minutes' => (int) ($summary['late_minutes'] ?? ($this->log?->late_minutes ?? 0)),
            'base_gain' => (float) $summary['base_gain'],
            'overtime_gain' => (float) $summary['overtime_gain'],
            'total_estimated' => (float) $summary['total_estimated'],
            'currency' => $summary['currency'],
        ];
    }
}
