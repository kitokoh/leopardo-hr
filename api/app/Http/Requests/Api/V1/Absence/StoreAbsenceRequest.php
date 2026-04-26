<?php

namespace App\Http\Requests\Api\V1\Absence;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'absence_type_id' => ['required', 'integer', 'exists:absence_types,id'],
            'start_date'      => ['required', 'date_format:Y-m-d'],
            'end_date'        => ['required', 'date_format:Y-m-d', 'gte:start_date'],
            'reason'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
