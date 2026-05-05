<?php

namespace App\Http\Requests\Api\V1\Absence;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbsenceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'status' => ['nullable', 'in:pending,approved,rejected,cancelled'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'min:2000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
