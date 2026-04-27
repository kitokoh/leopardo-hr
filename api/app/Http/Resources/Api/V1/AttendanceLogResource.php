<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'date' => $this->date?->format('Y-m-d'),
            'check_in' => $this->check_in?->toIso8601String(),
            'check_out' => $this->check_out?->toIso8601String(),
            'method' => $this->method,
            'source_device_code' => $this->source_device_code,
            'hours_worked' => $this->hours_worked,
            'overtime_hours' => $this->overtime_hours,
            'status' => $this->status,
            'late_minutes' => $this->late_minutes,
        ];
    }
}
