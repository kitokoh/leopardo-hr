<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Export;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'format' => 'nullable|in:json,csv',
        ];
    }
}
