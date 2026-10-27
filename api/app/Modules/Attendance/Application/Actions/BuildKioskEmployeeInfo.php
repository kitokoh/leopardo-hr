<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * #7962 — cas d'usage : fiche employé affichée au kiosque.
 *
 * Extrait de `KioskController::doEmployeeInfo()`. Regroupe la résolution de
 * l'employé par identifiant (matricule / email / zkteco_id), la présence du
 * jour et les soldes de congés — tout ce que le kiosque affiche après
 * identification.
 */
class BuildKioskEmployeeInfo
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function execute(Company $company, array $validated): array
    {
        $employee = $this->resolveEmployee($company, (string) $validated['identifier']);

        abort_if(! $employee, 404, 'EMPLOYEE_NOT_FOUND');

        $today = now()->toDateString();
        $todayAttendance = DB::table('attendance_logs')
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

        $leaveBalances = DB::table('leave_balances')
            ->where('employee_id', $employee->id)
            ->where('year', now()->year)
            ->get()
            ->map(fn ($balance) => $this->serializeLeaveBalance($balance));

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
                'matricule' => $employee->matricule,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'photo_url' => $employee->photo_url ?? null,
            ],
            'today_attendance' => $todayAttendance ? [
                'check_in' => $todayAttendance->check_in,
                'check_out' => $todayAttendance->check_out,
                'status' => $todayAttendance->status,
            ] : null,
            'leave_balances' => $leaveBalances,
            // PA2-KIO-004 : surface the employee's mobile-submitted biometric
            // enrollment consent/status on the kiosk so field staff can see
            // at a glance whether biometric punch is enabled/pending/rejected
            // for this employee, without leaving the kiosk screen.
            'biometric_enrollment' => $this->serializeBiometricEnrollmentStatus($employee),
        ];
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
     * @return array{face_enabled: bool, fingerprint_enabled: bool, consented_at: string|null, pending_request: bool, latest_request_status: string|null, latest_request_submitted_at: string|null}
     */
    private function serializeBiometricEnrollmentStatus(Employee $employee): array
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

    private function nullableIsoString(mixed $value): ?string
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
