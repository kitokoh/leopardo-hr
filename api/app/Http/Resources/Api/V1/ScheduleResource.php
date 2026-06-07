<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Schedule */
class ScheduleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'break_minutes' => $this->break_minutes,
            'work_days' => $this->work_days,
            'rest_days' => $this->rest_days ?? $this->restDaysFromWorkDays(),
            'break_rules' => $this->break_rules ?? [],
            'leave_rules' => $this->leave_rules ?? [],
            'assignment_notes' => $this->assignment_notes,
            'late_tolerance_minutes' => $this->late_tolerance_minutes,
            'overtime_threshold_daily' => $this->overtime_threshold_daily,
            'overtime_threshold_weekly' => $this->overtime_threshold_weekly,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return list<int> */
    private function restDaysFromWorkDays(): array
    {
        $workDays = is_array($this->work_days) ? array_map('intval', $this->work_days) : [1, 2, 3, 4, 5];

        return array_values(array_diff([1, 2, 3, 4, 5, 6, 7], $workDays));
    }
}
