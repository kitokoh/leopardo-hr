<?php

namespace App\Services;

use App\DTOs\CreateEmployeeDTO;
use App\DTOs\UpdateEmployeeDTO;
use App\Events\EmployeeArchived;
use App\Events\EmployeeCreated;
use App\Models\Employee;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeService
{
    public function __construct(
        private readonly UserInvitationService $userInvitationService,
    ) {}

    public function create(CreateEmployeeDTO $dto, ?Employee $actor = null): Employee
    {
        /** @var array<string, mixed> $payload */
        $payload = $dto->toArray();
        $sendInvitation = (bool) Arr::pull($payload, 'send_invitation', false);
        $providedPassword = Arr::pull($payload, 'password');
        $providedPassword = is_string($providedPassword) && $providedPassword !== '' ? $providedPassword : null;

        $companyId = $payload['company_id']
            ?? $actor?->company_id
            ?? (app()->bound('current_company') ? currentCompany()->id : null);

        $password = $providedPassword ?: Str::random(32);
        $payload['password_hash'] = Hash::make($password);
        $payload['contract_type'] = $payload['contract_type'] ?? 'CDI';
        $payload['contract_start'] = $payload['contract_start'] ?? now()->toDateString();
        $payload['hourly_rate'] = $payload['hourly_rate'] ?? 0.0;
        $payload['company_id'] = $companyId;

        if (empty($payload['role'])) {
            $payload['role'] = 'employee';
        }

        $payload['status'] = $payload['status'] ?? 'active';
        $payload['extra_data'] = $this->normalizeExtraData($this->arrayValue($payload, 'extra_data'));

        if ($actor?->isManager() && empty($payload['manager_id'])) {
            $payload['manager_id'] = $actor->id;
        }

        /** @var array<string, mixed> $payload */
        $this->applyBiometricConsent($payload);

        $employee = Employee::query()->create($payload);

        EmployeeCreated::dispatch($employee);

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

    public function update(Employee $actor, Employee $employee, UpdateEmployeeDTO $dto): Employee
    {
        /** @var array<string, mixed> $payload */
        $payload = $dto->toArray();
        $isManager = $actor->isManager();
        $isSelfUpdate = $actor->id === $employee->id;

        if (! $isManager) {
            /** @var array<string, mixed> $payload */
            $payload = Arr::only($payload, [
                'first_name',
                'last_name',
                'email',
                'personal_email',
                'recovery_email',
                'personal_phone',
                'password',
            ]);
        }

        $password = $this->stringValue($payload, 'password');
        if ($password !== null) {
            $employee->password_hash = Hash::make($password);
        }
        unset($payload['password']);

        if (! $isManager) {
            unset($payload['role'], $payload['manager_role'], $payload['status'], $payload['matricule']);
        }

        if ($isSelfUpdate) {
            unset($payload['role'], $payload['manager_role'], $payload['status'], $payload['manager_id']);
        }

        if (isset($payload['status']) && $payload['status'] === 'archived') {
            unset($payload['status']);
        }

        if (($payload['role'] ?? null) === 'employee') {
            $payload['manager_role'] = null;
        }

        if (array_key_exists('extra_data', $payload)) {
            $payload['extra_data'] = $this->normalizeExtraData($this->arrayValue($payload, 'extra_data'));
        }

        /** @var array<string, mixed> $payload */
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

        EmployeeArchived::dispatch($employee);

        return $employee;
    }

    /** @param array<string, mixed> $payload */
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

    /**
     * @param  array<string, mixed>  $extraData
     * @return array<string, mixed>
     */
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

        $normalized = [];

        foreach (Arr::only($extraData, $allowedKeys) as $key => $value) {
            if (is_string($key) && $value !== null && $value !== '') {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<string, mixed>
     */
    private function arrayValue(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $nestedKey => $nestedValue) {
            if (is_string($nestedKey)) {
                $normalized[$nestedKey] = $nestedValue;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function stringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
