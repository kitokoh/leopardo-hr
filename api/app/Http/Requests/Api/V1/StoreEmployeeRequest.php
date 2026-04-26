<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'contract_start' => ['nullable', 'date_format:Y-m-d'],
            'role' => ['nullable', 'in:employee,manager'],
            'manager_role' => ['nullable', 'in:principal,rh,dept,comptable,superviseur'],
            'phone' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:150'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d'],
            'place_of_birth' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', 'in:M,F'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'marital_status' => ['nullable', 'string', 'max:30'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:60'],
            'biometric_face_enabled' => ['nullable', 'boolean'],
            'biometric_fingerprint_enabled' => ['nullable', 'boolean'],
            'biometric_face_reference_path' => ['nullable', 'string', 'max:255'],
            'biometric_fingerprint_reference_path' => ['nullable', 'string', 'max:255'],
            'photo_path' => ['nullable', 'string', 'max:255'],
            'zkteco_id' => ['nullable', 'string', 'max:50'],
            'extra_data' => ['nullable', 'array'],
            'extra_data.department' => ['nullable', 'string', 'max:120'],
            'extra_data.job_title' => ['nullable', 'string', 'max:120'],
            'extra_data.work_location' => ['nullable', 'string', 'max:120'],
            'extra_data.national_id' => ['nullable', 'string', 'max:50'],
            'extra_data.tax_identifier' => ['nullable', 'string', 'max:50'],
            'extra_data.blood_group' => ['nullable', 'string', 'max:10'],
            'extra_data.education_level' => ['nullable', 'string', 'max:120'],
            'send_invitation' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $role = $this->input('role', 'employee');
            $password = $this->input('password');
            $sendInvitation = filter_var($this->input('send_invitation', false), FILTER_VALIDATE_BOOLEAN);

            if (! $password && ! $sendInvitation) {
                $validator->errors()->add('password', 'Le mot de passe ou l\'invitation email est requis.');
            }

            if ($role === 'manager' && ! $this->input('manager_role')) {
                $validator->errors()->add('manager_role', 'Le type de manager est requis.');
            }

            if ($role === 'manager' && $this->user()?->isManager() && $this->input('manager_role') === 'principal') {
                $validator->errors()->add('manager_role', 'Seul le super admin peut creer un manager principal.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $company = $this->user()?->company
            ?? (app()->bound('current_company') ? app('current_company') : null);

        if (! $company) {
            return;
        }

        if ($company->tenancy_type === 'schema' && $company->schema_name) {
            DB::statement('SET search_path TO '.\App\Models\Company::getSafeSearchPath($company->schema_name));

            return;
        }

        DB::statement('SET search_path TO '.\App\Models\Company::getSafeSearchPath('shared_tenants'));
    }
}
