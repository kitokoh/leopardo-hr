<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceTodayResource extends JsonResource
{
    private ?AttendanceLog $log;
    private string $timezone;

    /**
     * @param Employee $resource
     * @param AttendanceLog|null $log
     * @param string|null $timezone
     */
    public function __construct($resource, ?AttendanceLog $log = null, ?string $timezone = null)
    {
        parent::__construct($resource);
        $this->log = $log;
        $this->timezone = $timezone ?? app('current_company')->timezone;
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

        return [
            'employee_id' => $employee->id,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'checked_in' => (bool) $this->log?->check_in,
            'check_in_time' => $this->log?->check_in?->setTimezone($this->timezone)->format('H:i'),
            'check_out_time' => $this->log?->check_out?->setTimezone($this->timezone)->format('H:i'),
            'hours_worked' => $this->log?->hours_worked ?? '0.00',
            'status' => $this->log?->status ?? 'absent',
        ];
    }
}
