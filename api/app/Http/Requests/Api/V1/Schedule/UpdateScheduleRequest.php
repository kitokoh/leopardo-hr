<?php

namespace App\Http\Requests\Api\V1\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isManager();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['integer', 'between:1,7'],
            'rest_days' => ['nullable', 'array'],
            'rest_days.*' => ['integer', 'between:1,7'],
            'break_rules' => ['nullable', 'array', 'max:10'],
            'break_rules.*.label' => ['nullable', 'string', 'max:80'],
            'break_rules.*.start_time' => ['nullable', 'date_format:H:i'],
            'break_rules.*.end_time' => ['nullable', 'date_format:H:i'],
            'break_rules.*.minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'break_rules.*.is_paid' => ['nullable', 'boolean'],
            'leave_rules' => ['nullable', 'array', 'max:20'],
            'leave_rules.*.label' => ['nullable', 'string', 'max:120'],
            'leave_rules.*.type' => ['nullable', 'string', 'max:80'],
            'leave_rules.*.days_per_year' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'leave_rules.*.policy_id' => ['nullable', 'integer', 'min:1'],
            'assignment_notes' => ['nullable', 'string', 'max:1000'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'overtime_threshold_daily' => ['nullable', 'numeric', 'min:0'],
            'overtime_threshold_weekly' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
