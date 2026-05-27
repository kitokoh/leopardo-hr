<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class RequestCorrectionAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance_log_id' => ['nullable', 'integer', 'exists:attendance_logs,id'],
            'date' => ['required', 'date'],
            'requested_check_in' => ['required', 'date'],
            'requested_check_out' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
