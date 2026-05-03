<?php

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollIndexRequest extends FormRequest
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
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'min:2000'],
            'status' => ['nullable', 'in:draft,validated'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => 'Employé introuvable dans votre entreprise.',
        ];
    }
}
