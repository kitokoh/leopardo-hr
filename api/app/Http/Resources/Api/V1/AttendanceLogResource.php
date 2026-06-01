<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceLog
 */
class AttendanceLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $meta = is_array($this->punch_meta) ? $this->punch_meta : [];
        $timezone = (string) ($meta['server_timezone'] ?? currentCompany()->timezone);
        $deviceTimezone = $meta['device_timezone'] ?? null;

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'company_id' => $this->company_id,
            'employee' => $this->relationLoaded('employee') ? [
                'id' => $this->employee->id,
                'name' => trim(($this->employee->first_name ?? '').' '.($this->employee->last_name ?? '')),
                'matricule' => $this->employee->matricule,
                'photo_url' => $this->employee->photo_path,
            ] : null,
            'date' => $this->date?->format('Y-m-d'),
            'session_number' => (int) ($this->session_number ?? 1),
            'check_in' => $this->check_in?->toIso8601String(),
            'check_out' => $this->check_out?->toIso8601String(),
            'check_in_local' => $this->check_in?->copy()->setTimezone($timezone)->toIso8601String(),
            'check_out_local' => $this->check_out?->copy()->setTimezone($timezone)->toIso8601String(),
            'timezone' => $timezone,
            'device_timezone' => $deviceTimezone,
            'method' => $this->method,
            'work_type' => $this->work_type ?? 'normal',
            'punch_note' => $this->punch_note,
            'punch_meta' => $this->punch_meta,
            'source_device_code' => $this->source_device_code,
            'hours_worked' => $this->hours_worked,
            'overtime_hours' => $this->overtime_hours,
            'status' => $this->status,
            'late_minutes' => $this->late_minutes,
            'gps' => [
                'lat' => $this->gps_lat !== null ? (float) $this->gps_lat : null,
                'lng' => $this->gps_lng !== null ? (float) $this->gps_lng : null,
                'accuracy_m' => isset($meta['gps_accuracy']) ? (float) $meta['gps_accuracy'] : null,
            ],
            'geofence' => $meta['geofence'] ?? null,
        ];
    }
}
