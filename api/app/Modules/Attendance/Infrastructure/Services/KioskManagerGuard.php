<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;

/**
 * ATT-004 (#6769) / BIO-006 (#6767) — validation manager du pointage et des
 * décisions d'enrôlement kiosque.
 *
 * Le manager doit exister, être actif, appartenir au même tenant et porter
 * un rôle de manager (BIO-006). Point unique partagé par le pointage
 * (`KioskAttendanceService`) et le cycle d'enrôlement kiosque
 * (`KioskEnrollmentService`) — jamais de confiance dans l'interface.
 */
final class KioskManagerGuard
{
    /**
     * Valide un manager et le retourne.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 422/403
     */
    public function assertManager(AttendanceKiosk $kiosk, ?int $managerEmployeeId): Employee
    {
        if ($managerEmployeeId === null) {
            abort(422, 'MANAGER_VALIDATION_REQUIRED');
        }

        $manager = Employee::query()
            ->where('company_id', $kiosk->company_id)
            ->whereKey($managerEmployeeId)
            ->where('status', 'active')
            ->first();

        if (! $manager || ! $manager->isManager()) {
            abort(403, 'MANAGER_VALIDATION_REQUIRED');
        }

        return $manager;
    }
}
