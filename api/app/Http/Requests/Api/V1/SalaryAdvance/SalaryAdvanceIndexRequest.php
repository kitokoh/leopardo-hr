<?php

namespace App\Http\Requests\Api\V1\SalaryAdvance;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalaryAdvanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Employee|null $actor */
        $actor = $this->user();
        $companyId = $actor?->company_id;

        return [
            'employee_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $companyId),
            ],
            'status' => ['nullable', 'in:pending,approved,rejected,active,repaid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => 'EmployÃƒÂ© introuvable dans votre entreprise.',
        ];
    }
}
