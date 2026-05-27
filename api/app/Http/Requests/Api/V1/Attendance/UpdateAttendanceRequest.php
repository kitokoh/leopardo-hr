<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'work_type' => ['nullable', 'string', 'in:normal,overtime,break,resume,mission,travel,training,other'],
        ];
    }
}
