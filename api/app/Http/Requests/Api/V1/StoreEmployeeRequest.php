<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id
            ?? (app()->bound('current_company') ? app('current_company')->id : null);

        return [
            'matricule' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('employees', 'matricule')->where(
                    fn ($query) => $query->where('company_id', $companyId)
                ),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('employees', 'email'),
            ],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'contract_start' => ['nullable', 'date_format:Y-m-d'],
            'role' => ['nullable', 'in:employee,manager'],
        ];
    }
}
