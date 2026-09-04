<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Enums\BiometricEnrollmentStatus;
use App\Modules\Attendance\Domain\Enums\VerificationMethod;
use App\Modules\Attendance\Domain\Exceptions\DuplicatePendingBiometricEnrollmentException;
use App\Modules\Attendance\Domain\Exceptions\NonBiometricEnrollmentMethodException;
use App\Modules\Attendance\Domain\Models\BiometricEnrollment;
use App\Modules\Attendance\Domain\Support\BiometricEnrollmentStateMachine;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie des enrôlements biométriques versionnés (BIO-002, #6763).
 *
 * - `start()` : capture + gabarit fournis → enrôlement `pending` (version =
 *   dernière version + 1). Idempotent par `correlation_id` ; un second
 *   enrôlement pending sans corrélation est refusé.
 * - `activate()` : `pending` → `active` (autorisation manager/RH vérifiée
 *   par l'appelant/Policies) ; **révoque l'ancien gabarit actif** du même
 *   employé/méthode (remplacement versionné).
 * - `revoke()` : `pending|active` → `revoked` (RGPD, départ, compromission).
 *
 * Chaque transition est auditée (audit_logs, tenant-scoped) SANS gabarit ni
 * capture : seuls ids, méthode, statut, version et corrélation sont tracés.
 *
 * L'appelant doit exécuter dans le contexte tenant de l'employé
 * (TenantManager::withinTenant / middleware tenant) — le modèle porte le
 * scope global `BelongsToCompany` (fail-closed sur la surface API tenant).
 */
final class BiometricEnrollmentLifecycleService
{
    public function __construct(
        private readonly BiometricEnrollmentStateMachine $stateMachine,
        private readonly BiometricAuditLogger $biometricAudit,
    ) {}

    public function start(
        Employee $employee,
        VerificationMethod $method,
        string $templatePayload,
        string $provider,
        int $actorEmployeeId,
        string $enrolledVia = 'kiosk',
        ?string $correlationId = null,
    ): BiometricEnrollment {
        $this->assertStorableMethod($method);

        $companyId = (string) $employee->company_id;

        $pending = BiometricEnrollment::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employee->id)
            ->where('method', $method->value)
            ->where('status', BiometricEnrollmentStatus::Pending)
            ->latest('id')
            ->first();

        if ($pending !== null) {
            // Idempotence : même demande rejouée (corrélation identique) →
            // retour de l'enrôlement existant.
            if ($correlationId !== null && $pending->correlation_id === $correlationId) {
                return $pending;
            }

            throw new DuplicatePendingBiometricEnrollmentException;
        }

        $version = $this->nextVersion($employee, $method);

        return DB::transaction(function () use ($employee, $method, $templatePayload, $provider, $actorEmployeeId, $enrolledVia, $correlationId, $companyId, $version): BiometricEnrollment {
            $enrollment = BiometricEnrollment::query()->create([
                'company_id' => $companyId,
                'employee_id' => $employee->id,
                'method' => $method->value,
                'status' => BiometricEnrollmentStatus::Pending,
                'version' => $version,
                'template' => $templatePayload,
                'provider' => $provider,
                'correlation_id' => $correlationId,
                'enrolled_via' => $enrolledVia,
                'created_by_employee_id' => $actorEmployeeId,
            ]);

            $this->audit($enrollment, 'biometric.enrollment.started', $actorEmployeeId, [
                'version' => $version,
                'provider' => $provider,
                'enrolled_via' => $enrolledVia,
            ]);

            return $enrollment;
        });
    }

    public function activate(BiometricEnrollment $enrollment, int $actorEmployeeId): BiometricEnrollment
    {
        $this->stateMachine->assertCanTransition(
            $enrollment->status,
            BiometricEnrollmentStatus::Active,
        );

        return DB::transaction(function () use ($enrollment, $actorEmployeeId): BiometricEnrollment {
            // Remplacement : révoquer l'ancien gabarit actif AVANT d'activer
            // le nouveau (contrainte d'unicité de l'actif).
            BiometricEnrollment::query()
                ->where('company_id', $enrollment->company_id)
                ->where('employee_id', $enrollment->employee_id)
                ->where('method', $enrollment->method)
                ->where('status', BiometricEnrollmentStatus::Active)
                ->whereKeyNot($enrollment->id)
                ->update([
                    'status' => BiometricEnrollmentStatus::Revoked,
                    'revoked_at' => now(),
                    'revoked_by_employee_id' => $actorEmployeeId,
                ]);

            $enrollment->forceFill([
                'status' => BiometricEnrollmentStatus::Active,
                'activated_by_employee_id' => $actorEmployeeId,
                'enrolled_at' => now(),
            ])->save();

            $this->audit($enrollment, 'biometric.enrollment.activated', $actorEmployeeId);

            return $enrollment->fresh() ?? $enrollment;
        });
    }

    public function revoke(BiometricEnrollment $enrollment, int $actorEmployeeId): BiometricEnrollment
    {
        $this->stateMachine->assertCanTransition(
            $enrollment->status,
            BiometricEnrollmentStatus::Revoked,
        );

        $enrollment->forceFill([
            'status' => BiometricEnrollmentStatus::Revoked,
            'revoked_at' => now(),
            'revoked_by_employee_id' => $actorEmployeeId,
        ])->save();

        $this->audit($enrollment, 'biometric.enrollment.revoked', $actorEmployeeId);

        return $enrollment->fresh() ?? $enrollment;
    }

    private function assertStorableMethod(VerificationMethod $method): void
    {
        if (! $method->isBiometric()) {
            throw new NonBiometricEnrollmentMethodException(
                "Method '{$method->value}' has no biometric template to store."
            );
        }
    }

    private function nextVersion(Employee $employee, VerificationMethod $method): int
    {
        $max = BiometricEnrollment::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('method', $method->value)
            ->max('version');

        return is_numeric($max) ? (int) $max + 1 : 1;
    }

    /**
     * Trace une transition dans audit_logs (tenant-scoped, sans gabarit).
     *
     * @param  array<string, mixed>  $extra
     */
    private function audit(BiometricEnrollment $enrollment, string $action, int $actorEmployeeId, array $extra = []): void
    {
        // BIO-008 (#6773) : audit biométrique dédié (ids + codes uniquement).
        $this->biometricAudit->log(
            companyId: $enrollment->company_id,
            event: $action,
            employeeId: (int) $enrollment->employee_id,
            actorEmployeeId: $actorEmployeeId,
            method: $enrollment->method,
            correlationId: $enrollment->correlation_id,
            context: $extra,
        );

        $metadata = array_merge([
            'enrollment_id' => $enrollment->id,
            'employee_id' => $enrollment->employee_id,
            'method' => $enrollment->method,
            'status' => $enrollment->status->value,
            'version' => $enrollment->version,
            'actor_employee_id' => $actorEmployeeId,
            'correlation_id' => $enrollment->correlation_id,
            // Redaction BIO-008 : aucun gabarit, aucune capture, aucun secret.
        ], $extra);

        AuditLog::query()->create([
            'company_id' => $enrollment->company_id,
            'user_id' => null,
            'action' => $action,
            'module' => 'attendance',
            'auditable_type' => BiometricEnrollment::class,
            'auditable_id' => $enrollment->id,
            'metadata' => $metadata,
        ]);
    }
}
