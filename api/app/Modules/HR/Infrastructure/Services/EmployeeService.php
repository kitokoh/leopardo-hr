<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Modules\HR\Application\DTOs\CreateEmployeeDTO;
use App\Modules\HR\Application\DTOs\UpdateEmployeeDTO;
use App\Events\EmployeeArchived;
use App\Events\EmployeeCreated;
use App\Events\EmployeeRoleAssigned;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Infrastructure\Services\TenantCacheService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeService
{
    public function __construct(
        private readonly UserInvitationService $userInvitationService,
        private readonly TenantCacheService $tenantCache,
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

        // #4428 / #3677 : company_id, role, manager_role, status et salary_base
        // ne sont plus mass-assignables depuis le durcissement fillable #3677 —
        // les passer à create() les ferait abandonner SILENCIEUSEMENT (employé
        // orphelin : company_id NULL, rôle NULL, invisible du manager).
        // Pattern #4151/#4079 : create([...fillable...]) puis assignation
        // explicite des clés sensibles + save().
        $sensitive = Arr::only($payload, [
            'company_id',
            'role',
            'manager_role',
            'status',
            'salary_base',
        ]);

        /** @var array<string, mixed> $fillablePayload */
        $fillablePayload = Arr::except($payload, [
            'company_id',
            'role',
            'manager_role',
            'status',
            'salary_base',
        ]);

        /** @var Employee $employee */
        $employee = Employee::query()->create($fillablePayload);

        // Assignation explicite des clés sensibles (pattern #4151) — jamais
        // dans create()/fill(), sinon abandon silencieux (#4428).
        if (array_key_exists('company_id', $sensitive) && $sensitive['company_id'] !== null) {
            $employee->company_id = $sensitive['company_id'];
        }
        if (array_key_exists('role', $sensitive) && $sensitive['role'] !== null) {
            $employee->role = $sensitive['role'];
        }
        if (array_key_exists('manager_role', $sensitive) && $sensitive['manager_role'] !== null) {
            $employee->manager_role = $sensitive['manager_role'];
        }
        if (array_key_exists('status', $sensitive) && $sensitive['status'] !== null) {
            $employee->status = $sensitive['status'];
        }
        if (array_key_exists('salary_base', $sensitive) && $sensitive['salary_base'] !== null) {
            $employee->salary_base = $sensitive['salary_base'];
        }
        $employee->save();

        if ($employee->company_id !== null) {
            $this->tenantCache->invalidateEmployees($employee->company_id);
        }

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

        $previousManagerRole = $employee->manager_role;
        $roleChangeRequested = array_key_exists('manager_role', $payload) || array_key_exists('role', $payload);

        // #4428 / #3677 : role, manager_role, status et salary_base ne sont
        // plus mass-assignables — fill() les abandonnerait silencieusement
        // (ex. PATCH salary_base depuis le mobile manager = 0 jamais persisté).
        // Pattern #4151 : fill([...fillable...]) puis assignation explicite.
        $sensitiveUpdate = Arr::only($payload, [
            'role',
            'manager_role',
            'status',
            'salary_base',
        ]);

        /** @var array<string, mixed> $fillableUpdate */
        $fillableUpdate = Arr::except($payload, [
            'role',
            'manager_role',
            'status',
            'salary_base',
        ]);

        $employee->fill($fillableUpdate);

        if (array_key_exists('role', $sensitiveUpdate)) {
            $employee->role = $sensitiveUpdate['role'];
        }
        if (array_key_exists('manager_role', $sensitiveUpdate)) {
            $employee->manager_role = $sensitiveUpdate['manager_role'];
        }
        if (array_key_exists('status', $sensitiveUpdate)) {
            $employee->status = $sensitiveUpdate['status'];
        }
        if (array_key_exists('salary_base', $sensitiveUpdate) && $sensitiveUpdate['salary_base'] !== null) {
            $employee->salary_base = $sensitiveUpdate['salary_base'];
        }
        $employee->save();

        if ($employee->company_id !== null) {
            $this->tenantCache->invalidateEmployees($employee->company_id);
        }

        // PA2-MOB-007 — nominate/revoke RH permissions must leave an audit
        // trail even when the change is made through the generic employee
        // update endpoint (e.g. from the manager mobile app) rather than the
        // dedicated RoleAssignmentController::assign endpoint.
        if ($roleChangeRequested && $employee->manager_role !== $previousManagerRole) {
            EmployeeRoleAssigned::dispatch($employee, $actor, $previousManagerRole, $employee->manager_role);
        }

        return $employee;
    }

    public function archive(Employee $employee): Employee
    {
        $employee->status = 'archived';
        $employee->save();
        $employee->tokens()->delete();

        if ($employee->company_id !== null) {
            $this->tenantCache->invalidateEmployees($employee->company_id);
        }

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

