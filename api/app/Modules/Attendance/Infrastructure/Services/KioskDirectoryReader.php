<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * #8944 (sous-tâche de #7962) — lecture des données du kiosque.
 *
 * Extrait de `KioskController` : la requête et la sérialisation vivaient dans
 * le contrôleur. Elles vivent désormais ici, en Infrastructure, parce que
 * `check-layer-purity` (issue #6568) interdit les facades Laravel (`DB`,
 * `Schema`) dans `Application/Actions` — les Actions restent donc pures et
 * orchestrent ce lecteur.
 *
 * Comportement inchangé, y compris la tolérance aux schémas historiques : les
 * colonnes biométriques peuvent manquer sur un tenant ancien, auquel cas le
 * roster n'est pas filtré et les drapeaux valent `false`.
 */
class KioskDirectoryReader
{
    /**
     * Roster biométrique du kiosque.
     *
     * @return array<int, array<string, mixed>>
     */
    public function roster(Company $company): array
    {
        $hasFaceColumn = Schema::hasColumn('employees', 'biometric_face_enabled');
        $hasFingerprintColumn = Schema::hasColumn('employees', 'biometric_fingerprint_enabled');

        return Employee::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->when($hasFaceColumn || $hasFingerprintColumn, function ($query) use ($hasFaceColumn, $hasFingerprintColumn): void {
                $query->where(function ($biometricQuery) use ($hasFaceColumn, $hasFingerprintColumn): void {
                    if ($hasFaceColumn) {
                        $biometricQuery->orWhere('biometric_face_enabled', true);
                    }

                    if ($hasFingerprintColumn) {
                        $biometricQuery->orWhere('biometric_fingerprint_enabled', true);
                    }
                });
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Employee $employee) => $this->serializeRosterEmployee($employee, $hasFaceColumn, $hasFingerprintColumn))
            ->values()
            ->all();
    }

    /**
     * Résolution tenant-scopée : un identifiant d'une autre société ne doit
     * jamais être résolu (le kiosque est authentifié, mais l'identifiant vient
     * du terrain).
     */
    public function resolveEmployee(Company $company, string $identifier): ?Employee
    {
        return Employee::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($identifier): void {
                $query->where('matricule', $identifier)
                    ->orWhere('email', $identifier)
                    ->orWhere('zkteco_id', $identifier);
            })
            ->first();
    }

    public function todayAttendance(int $employeeId): ?object
    {
        return DB::table('attendance_logs')
            ->where('employee_id', $employeeId)
            ->where('date', now()->toDateString())
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function leaveBalances(int $employeeId): array
    {
        return DB::table('leave_balances')
            ->where('employee_id', $employeeId)
            ->where('year', now()->year)
            ->get()
            ->map(fn ($balance) => $this->serializeLeaveBalance($balance))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRosterEmployee(Employee $employee, bool $hasFaceColumn, bool $hasFingerprintColumn): array
    {
        return [
            'employee_id' => $employee->id,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'email' => $employee->email,
            'matricule' => $employee->matricule,
            'zkteco_id' => $employee->zkteco_id,
            // ATT-004 (#6769) : badge exposé au kiosque (badge/PIN/validation
            // manager) — l'identifiant de pointage par carte est déjà accepté
            // par punch (BIO-006). L'appareil est authentifié (X-Kiosk-Token).
            'badge_number' => $employee->badge_number,
            'face_enabled' => $hasFaceColumn ? (bool) ($employee->biometric_face_enabled ?? false) : false,
            'fingerprint_enabled' => $hasFingerprintColumn ? (bool) ($employee->biometric_fingerprint_enabled ?? false) : false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeLeaveBalance(object $balance): array
    {
        $remaining = (float) ($balance->remaining ?? $balance->balance ?? 0);
        $used = (float) ($balance->used_days ?? $balance->used ?? 0);
        $pending = (float) ($balance->pending ?? 0);
        $total = (float) ($balance->total_days ?? $balance->entitled_days ?? ($remaining + $used + $pending));

        return [
            'leave_type' => (string) ($balance->leave_type ?? $balance->absence_type_id ?? 'annual'),
            'total' => $total,
            'used' => $used,
            'pending' => $pending,
            'remaining' => $remaining,
        ];
    }

    /**
     * PA2-KIO-004 : statut d'enrôlement biométrique affiché au kiosque.
     *
     * @return array{face_enabled: bool, fingerprint_enabled: bool, consented_at: string|null, pending_request: bool, latest_request_status: string|null, latest_request_submitted_at: string|null}
     */
    public function biometricEnrollmentStatus(Employee $employee): array
    {
        $hasFaceColumn = Schema::hasColumn('employees', 'biometric_face_enabled');
        $hasFingerprintColumn = Schema::hasColumn('employees', 'biometric_fingerprint_enabled');

        $faceEnabled = $hasFaceColumn ? (bool) ($employee->biometric_face_enabled ?? false) : false;
        $fingerprintEnabled = $hasFingerprintColumn ? (bool) ($employee->biometric_fingerprint_enabled ?? false) : false;

        $latestRequest = null;
        if (Schema::hasTable('biometric_enrollment_requests')) {
            $latestRequest = BiometricEnrollmentRequest::query()
                ->where('employee_id', $employee->id)
                ->latest('id')
                ->first();
        }

        return [
            'face_enabled' => $faceEnabled,
            'fingerprint_enabled' => $fingerprintEnabled,
            'consented_at' => $this->nullableIsoString($employee->biometric_consent_at ?? null),
            'pending_request' => $latestRequest && $latestRequest->status === 'pending',
            'latest_request_status' => $latestRequest?->status,
            'latest_request_submitted_at' => $latestRequest ? $this->nullableIsoString($latestRequest->submitted_at) : null,
        ];
    }

    public function nullableIsoString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (Throwable) {
            return (string) $value;
        }
    }
}
