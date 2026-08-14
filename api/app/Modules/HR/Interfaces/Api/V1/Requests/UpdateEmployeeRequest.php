<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use App\Core\Tenant\Domain\Models\Company;
use App\Rules\GlobalEmailUnique;
use App\Rules\ValidIban;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
            ?? (app()->bound('current_company') ? currentCompany()->id : null);

        return [
            'matricule' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('employees', 'matricule')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($employeeId),
            ],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:150',
                Rule::unique('employees', 'email')->ignore($employeeId),
                new GlobalEmailUnique((int) $employeeId),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'contract_start' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'schedule_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('schedules', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'salary_type' => ['sometimes', 'nullable', 'in:fixed,hourly,daily'],
            'salary_base' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'role' => ['sometimes', 'nullable', 'in:employee,manager'],
            'manager_role' => ['sometimes', 'nullable', 'in:principal,rh,dept,comptable,superviseur,marketing'],
            'status' => ['sometimes', 'nullable', 'in:active,suspended'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'personal_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'iban' => ['sometimes', 'nullable', 'string', 'max:34', new ValidIban],
            'bank_account' => ['sometimes', 'nullable', 'string', 'max:34'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'preferred_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'date_of_birth' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'place_of_birth' => ['sometimes', 'nullable', 'string', 'max:120'],
            'gender' => ['sometimes', 'nullable', 'in:M,F'],
            'nationality' => ['sometimes', 'nullable', 'string', 'size:2'],
            'marital_status' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address_line' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'emergency_contact_relation' => ['sometimes', 'nullable', 'string', 'max:60'],
            'biometric_face_enabled' => ['sometimes', 'nullable', 'boolean'],
            'biometric_fingerprint_enabled' => ['sometimes', 'nullable', 'boolean'],
            'biometric_face_reference_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'biometric_fingerprint_reference_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'photo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'zkteco_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'extra_data' => ['sometimes', 'nullable', 'array'],
            'extra_data.department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'extra_data.job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'extra_data.work_location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'extra_data.national_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'extra_data.tax_identifier' => ['sometimes', 'nullable', 'string', 'max:50'],
            'extra_data.blood_group' => ['sometimes', 'nullable', 'string', 'max:10'],
            'extra_data.education_level' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $user = auth('sanctum')->user() ?? $this->user();

            if (($this->has('role') || $this->has('manager_role')) &&
                $user?->isManager() &&
                ! $user->hasManagerRole('principal')) {
                $validator->errors()->add(
                    'role',
                    'Seul le manager principal peut modifier les roles RH.'
                );
            }

            // Seul le super admin peut promouvoir un employe en manager principal :
            // meme un RH ne doit pas pouvoir PATCH un employe en manager_role=principal
            // (idem que la regle deja appliquee cote StoreEmployeeRequest).
            if ($this->input('manager_role') === 'principal' && $user?->isManager()) {
                $validator->errors()->add(
                    'manager_role',
                    'Seul le super admin peut promouvoir un manager en principal.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // Issue #1765 (chemin PUT — même classe de TypeError que le POST :
        // UpdateEmployeeDTO::$salary_base int|float, ?bool biometric_*) :
        // normalisation des champs numériques et booléens AVANT la validation.
        foreach (['salary_base', 'hourly_rate'] as $numericField) {
            $value = $this->input($numericField);

            if ($value !== null && $value !== '' && is_numeric($value)) {
                $this->merge([$numericField => (float) $value]);
            }
        }

        $scheduleId = $this->input('schedule_id');
        if ($scheduleId !== null && $scheduleId !== '' && is_numeric($scheduleId)) {
            $this->merge(['schedule_id' => (int) $scheduleId]);
        }

        foreach (['biometric_face_enabled', 'biometric_fingerprint_enabled'] as $boolField) {
            $value = $this->input($boolField);

            if (is_bool($value) || (is_int($value) && in_array($value, [0, 1], true))) {
                continue;
            }

            if (in_array($value, ['1', '0', 'true', 'false', 'on', 'off', 'yes', 'no'], true)) {
                $this->merge([$boolField => filter_var($value, FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $company = $this->user()?->company
            ?? (app()->bound('current_company') ? currentCompany() : null);

        if (! $company instanceof Company) {
            return;
        }

        $schemaName = $company->schema_name;

        if ($company->tenancy_type === 'schema' && $schemaName !== '') {
            DB::statement('SET search_path TO '.$company->getSafeSearchPath());

            return;
        }

        DB::statement('SET search_path TO shared_tenants,public');
    }
}
