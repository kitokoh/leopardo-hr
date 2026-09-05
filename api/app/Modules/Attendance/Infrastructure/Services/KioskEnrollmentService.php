<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\TenantManager;
use App\Modules\Attendance\Domain\Enums\BiometricEnrollmentStatus;
use App\Modules\Attendance\Domain\Enums\VerificationMethod;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Domain\Models\BiometricEnrollment;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * ATT-004 (#6769) — enrôlement biométrique piloté par le kiosque.
 *
 * Expose le cycle de vie versionné (BIO-002 #6763 / BIO-003 #6764) derrière
 * les routes kiosque tenant-scoped :
 *   - `start()` : capture fournie par le kiosque → enrôlement `pending`
 *     (gabarit chiffré au repos, idempotent par corrélation) ;
 *   - `activate()` : validation manager (KioskManagerGuard) → `active`, avec
 *     activation du flag employé correspondant (face/empreinte) pour que la
 *     matrice BIO-006 accepte la méthode ;
 *   - `revoke()` : validation manager → `revoked` + flag employé retiré ;
 *   - `status()` : état neutre (statuts + versions, jamais de gabarit).
 *
 * Toutes les opérations sont exécutées dans le contexte tenant du kiosque.
 */
final class KioskEnrollmentService
{
    public function __construct(
        private readonly BiometricEnrollmentLifecycleService $lifecycle,
        private readonly KioskManagerGuard $managerGuard,
        private readonly TenantManager $tenantManager,
    ) {}

    /**
     * Démarre un enrôlement pour l'employé identifié (résolu dans le tenant
     * du kiosque).
     *
     * @return array{enrollment: BiometricEnrollment, employee: Employee}
     */
    public function start(
        AttendanceKiosk $kiosk,
        string $identifier,
        string $method,
        string $templatePayload,
        string $provider,
        ?string $correlationId,
    ): array {
        return $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $identifier, $method, $templatePayload, $provider, $correlationId): array {
            $employee = $this->resolveEmployee($kiosk, $identifier);
            $verificationMethod = $this->assertEnrollableMethod($method, $kiosk);

            $enrollment = $this->lifecycle->start(
                employee: $employee,
                method: $verificationMethod,
                templatePayload: $templatePayload,
                provider: $provider,
                actorEmployeeId: (int) $employee->id,
                enrolledVia: 'kiosk',
                correlationId: $correlationId,
            );

            return ['enrollment' => $enrollment, 'employee' => $employee];
        });
    }

    /**
     * Active un enrôlement `pending` après validation manager.
     */
    public function activate(AttendanceKiosk $kiosk, BiometricEnrollment $enrollment, int $managerEmployeeId): BiometricEnrollment
    {
        return $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $enrollment, $managerEmployeeId): BiometricEnrollment {
            $manager = $this->managerGuard->assertManager($kiosk, $managerEmployeeId);
            $this->assertEnrollmentInKioskTenant($kiosk, $enrollment);

            $activated = $this->lifecycle->activate($enrollment, (int) $manager->id);

            // BIO-006 (#6767) : l'activation rend la méthode réellement
            // utilisable (la vérification exige le flag employé).
            $this->setEmployeeBiometricFlag($enrollment, true);

            return $activated;
        });
    }

    /**
     * Révoque un enrôlement (RGPD, départ, compromission, remplacement)
     * après validation manager.
     */
    public function revoke(AttendanceKiosk $kiosk, BiometricEnrollment $enrollment, int $managerEmployeeId): BiometricEnrollment
    {
        return $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $enrollment, $managerEmployeeId): BiometricEnrollment {
            $manager = $this->managerGuard->assertManager($kiosk, $managerEmployeeId);
            $this->assertEnrollmentInKioskTenant($kiosk, $enrollment);

            $revoked = $this->lifecycle->revoke($enrollment, (int) $manager->id);

            // Plus aucun actif pour cette méthode → flag retiré (la matrice
            // BIO-006 refusera la méthode biométrique correspondante).
            $stillActive = BiometricEnrollment::query()
                ->where('company_id', $enrollment->company_id)
                ->where('employee_id', $enrollment->employee_id)
                ->where('method', $enrollment->method)
                ->where('status', BiometricEnrollmentStatus::Active)
                ->exists();

            if (! $stillActive) {
                $this->setEmployeeBiometricFlag($enrollment, false);
            }

            return $revoked;
        });
    }

    /**
     * État d'enrôlement neutre d'un employé (face + empreinte).
     *
     * @return array<string, mixed>
     */
    public function status(AttendanceKiosk $kiosk, string $identifier): array
    {
        return $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $identifier): array {
            $employee = $this->resolveEmployee($kiosk, $identifier);

            $enrollments = BiometricEnrollment::query()
                ->where('company_id', $kiosk->company_id)
                ->where('employee_id', $employee->id)
                ->orderByDesc('version')
                ->get()
                ->keyBy('method');

            $statuses = [];
            foreach ([VerificationMethod::Face, VerificationMethod::Fingerprint] as $method) {
                /** @var BiometricEnrollment|null $enrollment */
                $enrollment = $enrollments->get($method->value);
                $statuses[] = [
                    'method' => $method->value,
                    'status' => $enrollment?->status->value ?? 'none',
                    'version' => $enrollment?->version,
                    'enrolled_at' => $enrollment?->enrolled_at?->toIso8601String(),
                    'enabled' => $method === VerificationMethod::Face
                        ? (bool) $employee->biometric_face_enabled
                        : (bool) $employee->biometric_fingerprint_enabled,
                ];
            }

            return [
                'employee_id' => $employee->id,
                'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
                'enrollments' => $statuses,
            ];
        });
    }

    private function resolveEmployee(AttendanceKiosk $kiosk, string $identifier): Employee
    {
        $employee = Employee::query()
            ->where('company_id', $kiosk->company_id)
            ->where(function ($query) use ($identifier): void {
                $query
                    ->where('email', $identifier)
                    ->orWhere('matricule', $identifier)
                    ->orWhere('zkteco_id', $identifier)
                    ->orWhere('badge_number', $identifier);
            })
            ->first();

        if (! $employee) {
            throw (new ModelNotFoundException)->setModel(Employee::class);
        }

        return $employee;
    }

    /**
     * Méthode enrôlable (face/empreinte) ET autorisée par la matrice du
     * kiosque (BIO-006).
     */
    private function assertEnrollableMethod(string $method, AttendanceKiosk $kiosk): VerificationMethod
    {
        $verificationMethod = VerificationMethod::tryFrom($method);

        if ($verificationMethod === null || ! $verificationMethod->isBiometric()) {
            abort(422, 'PUNCH_METHOD_NOT_CONFIGURED');
        }

        if (! $kiosk->isPunchMethodAllowed($verificationMethod->value)) {
            abort(422, 'PUNCH_METHOD_NOT_CONFIGURED');
        }

        return $verificationMethod;
    }

    private function assertEnrollmentInKioskTenant(AttendanceKiosk $kiosk, BiometricEnrollment $enrollment): void
    {
        // QLT-001 (#6775) : un kiosque du tenant A ne peut jamais agir sur un
        // enrôlement du tenant B (scope global BelongsToCompany).
        if ((string) $enrollment->company_id !== (string) $kiosk->company_id) {
            abort(404, 'ENROLLMENT_NOT_FOUND');
        }
    }

    private function setEmployeeBiometricFlag(BiometricEnrollment $enrollment, bool $enabled): void
    {
        $employee = Employee::query()
            ->where('company_id', $enrollment->company_id)
            ->whereKey($enrollment->employee_id)
            ->first();

        if (! $employee) {
            return;
        }

        $flag = $enrollment->method === VerificationMethod::Face->value
            ? 'biometric_face_enabled'
            : 'biometric_fingerprint_enabled';

        $employee->forceFill([$flag => $enabled])->save();
    }
}
