<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee');
        $companyId = $this->user()?->company_id
            ?? (app()->bound('current_company') ? app('current_company')->id : null);

        return [
            'matricule' => ['sometimes', 'nullable', 'string', 'max:20'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:150',
                Rule::unique('employees', 'email')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($employeeId),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'role' => ['sometimes', 'nullable', 'in:employee,manager'],
            'status' => ['sometimes', 'nullable', 'in:active,suspended'],
        ];
    }
}
