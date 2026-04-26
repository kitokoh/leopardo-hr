<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'date' => $this->date,
            'check_in' => $this->check_in?->toIso8601String(),
            'check_out' => $this->check_out?->toIso8601String(),
            'status' => $this->status,
            'late_minutes' => $this->late_minutes,
            'hours_worked' => (float) $this->hours_worked,
            'overtime_hours' => (float) $this->overtime_hours,
            'method' => $this->method,
        ];
    }
}
