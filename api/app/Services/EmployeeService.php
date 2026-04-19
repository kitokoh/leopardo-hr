<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeService
{
    public function __construct(
        private readonly UserInvitationService $userInvitationService,
    ) {
    }

    public function create(array $payload, ?Employee $actor = null): Employee
    {
        $sendInvitation = (bool) Arr::pull($payload, 'send_invitation', false);
        $providedPassword = Arr::pull($payload, 'password');
        $companyId = $payload['company_id']
            ?? $actor?->company_id
            ?? (app()->bound('current_company') ? app('current_company')->id : null);
        $password = $providedPassword ?: Str::random(32);
        $payload['password_hash'] = Hash::make($password);
        $payload['contract_start'] = $payload['contract_start'] ?? now()->toDateString();
        $payload['company_id'] = $companyId;

        if (empty($payload['role'])) {
            $payload['role'] = 'employee';
        }

        $payload['status'] = $payload['status'] ?? 'active';
        $payload['extra_data'] = $this->normalizeExtraData($payload['extra_data'] ?? []);

        if ($actor?->isManager() && empty($payload['manager_id']) && $payload['role'] !== 'manager') {
            $payload['manager_id'] = $actor->id;
        }

        if ($actor?->isManager() && empty($payload['manager_id']) && $payload['role'] === 'manager') {
            $payload['manager_id'] = $actor->id;
        }

        $this->applyBiometricConsent($payload);

        $employee = Employee::query()->create($payload);

        if ($sendInvitation || ! $providedPassword) {
            $company = $employee->company;
            if ($company && $actor) {
                $this->userInvitationService->createAndSend(
                    company: $company,
                    employee: $employee,
                    invitedByType: 'manager',
                    invitedByEmail: $actor->email,
                );
            }
        }

        return $employee;
    }

    public function update(Employee $actor, Employee $employee, array $payload): Employee
    {
        $isManager = $actor->isManager();

        if (! $isManager) {
            $payload = Arr::only($payload, ['first_name', 'last_name', 'email', 'password']);
        }

        if (array_key_exists('password', $payload) && $payload['password']) {
            $employee->password_hash = Hash::make($payload['password']);
        }
        unset($payload['password']);

        if (! $isManager) {
            unset($payload['role'], $payload['manager_role'], $payload['status'], $payload['matricule']);
        }

        if (array_key_exists('extra_data', $payload)) {
            $payload['extra_data'] = $this->normalizeExtraData($payload['extra_data'] ?? []);
        }

        $this->applyBiometricConsent($payload, $employee);

        $employee->fill($payload);
        $employee->save();

        return $employee;
    }

    public function archive(Employee $employee): Employee
    {
        $employee->status = 'archived';
        $employee->save();
        $employee->tokens()->delete();

        return $employee;
    }

    private function applyBiometricConsent(array &$payload, ?Employee $employee = null): void
    {
        $faceEnabled = array_key_exists('biometric_face_enabled', $payload)
            ? (bool) $payload['biometric_face_enabled']
            : (bool) $employee?->biometric_face_enabled;
        $fingerprintEnabled = array_key_exists('biometric_fingerprint_enabled', $payload)
            ? (bool) $payload['biometric_fingerprint_enabled']
            : (bool) $employee?->biometric_fingerprint_enabled;
        $hasReferences = ! empty($payload['biometric_face_reference_path'] ?? $employee?->biometric_face_reference_path)
            || ! empty($payload['biometric_fingerprint_reference_path'] ?? $employee?->biometric_fingerprint_reference_path);

        if ($faceEnabled || $fingerprintEnabled || $hasReferences) {
            $payload['biometric_consent_at'] = $payload['biometric_consent_at'] ?? $employee?->biometric_consent_at ?? now();
        }
    }

    private function normalizeExtraData(array $extraData): array
    {
        $allowedKeys = [
            'department',
            'job_title',
            'work_location',
            'national_id',
            'tax_identifier',
            'blood_group',
            'education_level',
        ];

        return array_filter(
            Arr::only($extraData, $allowedKeys),
            static fn ($value): bool => $value !== null && $value !== ''
        );
    }
}
