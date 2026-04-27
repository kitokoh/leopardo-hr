<?php

namespace App\Http\Requests\Api\V1\Absence;

use Illuminate\Foundation\Http\FormRequest;

class RejectAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejected_reason' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }
}
