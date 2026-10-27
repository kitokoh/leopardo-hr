<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Infrastructure\Services\KioskDirectoryReader;

/**
 * #8944 (sous-tâche de #7962) — cas d'usage : fiche employé affichée au kiosque.
 *
 * Extrait de `KioskController::doEmployeeInfo()`. Regroupe la résolution de
 * l'employé par identifiant (matricule / email / zkteco_id), la présence du
 * jour et les soldes de congés — tout ce que le kiosque affiche après
 * identification. L'accès aux données vit dans `KioskDirectoryReader`
 * (Infrastructure) : `check-layer-purity` (#6568) interdit les facades
 * Laravel dans Application.
 */
class BuildKioskEmployeeInfo
{
    public function __construct(private readonly KioskDirectoryReader $reader) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function execute(Company $company, array $validated): array
    {
        $employee = $this->resolveEmployee($company, (string) $validated['identifier']);

        abort_if(! $employee, 404, 'EMPLOYEE_NOT_FOUND');

        $todayAttendance = $this->reader->todayAttendance($employee->id);
        // `todayAttendance()` renvoie une ligne `DB::table()` (stdClass) : on la
        // normalise en tableau pour que PHPStan (level 8) ne voie pas d'accès à
        // une propriété non déclarée sur `object`.
        $todayAttendanceRow = $todayAttendance !== null ? (array) $todayAttendance : null;

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
                'matricule' => $employee->matricule,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'photo_url' => $employee->photo_url ?? null,
            ],
            'today_attendance' => $todayAttendanceRow !== null ? [
                'check_in' => $todayAttendanceRow['check_in'] ?? null,
                'check_out' => $todayAttendanceRow['check_out'] ?? null,
                'status' => $todayAttendanceRow['status'] ?? null,
            ] : null,
            'leave_balances' => $this->reader->leaveBalances($employee->id),
            // PA2-KIO-004 : surface the employee's mobile-submitted biometric
            // enrollment consent/status on the kiosk so field staff can see
            // at a glance whether biometric punch is enabled/pending/rejected
            // for this employee, without leaving the kiosk screen.
            'biometric_enrollment' => $this->reader->biometricEnrollmentStatus($employee),
        ];
    }

    /**
     * Résolution tenant-scopée : un identifiant d'une autre société ne doit
     * jamais être résolu (le kiosque est authentifié, mais l'identifiant vient
     * du terrain).
     */
    public function resolveEmployee(Company $company, string $identifier): ?Employee
    {
        return $this->reader->resolveEmployee($company, $identifier);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeLeaveBalance(object $balance): array
    {
        return $this->reader->serializeLeaveBalance($balance);
    }
}
