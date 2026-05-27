<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Kiosk;

use Illuminate\Foundation\Http\FormRequest;

class SyncAttendanceZktecoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'records' => ['required', 'array'],
            'records.*.user_id' => ['required', 'string'],
            'records.*.timestamp' => ['required', 'date'],
            'records.*.punch_type' => ['nullable', 'integer', 'min:0', 'max:5'],
            'records.*.badge_number' => ['nullable', 'string'],
        ];
    }
}
