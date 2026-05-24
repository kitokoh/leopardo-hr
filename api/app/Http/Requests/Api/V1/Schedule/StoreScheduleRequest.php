<?php

namespace App\Http\Requests\Api\V1\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isManager();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['integer', 'between:1,7'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'overtime_threshold_daily' => ['nullable', 'numeric', 'min:0'],
            'overtime_threshold_weekly' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
