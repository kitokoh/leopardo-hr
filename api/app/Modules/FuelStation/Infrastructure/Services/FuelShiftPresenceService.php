<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Résolution de présence opérateur pour les shifts FuelStation
 * (FUEL-006, issue #5800).
 *
 * Ne DUPLIQUE PAS la logique Attendance : lit la source canonique
 * `attendance_logs` (une ligne par employé/jour/session, statut
 * ontime|late|absent|leave|holiday|incomplete, check_in/check_out stockés
 * en UTC) via `DB::table()` — sans import inter-modules (isolation #5584).
 *
 * Règles :
 * - `present` si une ligne de pointage existe ce jour (statut ontime/late) ;
 * - `late` si le statut canonique est `late` (ou late_minutes > 0 sur un
 *   statut ontime) ;
 * - `absent` / `leave` / `holiday` / `incomplete` : statut canonique reporté ;
 * - `outside_shift` : pointage présent mais check_in (timezone entreprise)
 *   hors de la fenêtre [start_time, end_time] du shift affecté.
 */
final class FuelShiftPresenceService
{
    public const STATUS_PRESENT = 'present';

    public const STATUS_LATE = 'late';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LEAVE = 'leave';

    public const STATUS_HOLIDAY = 'holiday';

    public const STATUS_INCOMPLETE = 'incomplete';

    /**
     * Résout la présence d'un employé pour une date donnée.
     *
     * @return array{
     *     employee_id: int,
     *     status: string,
     *     check_in: string|null,
     *     check_out: string|null,
     *     hours_worked: float|null,
     *     late_minutes: int,
     *     outside_shift: bool
     * }
     */
    public function resolveForEmployee(
        string $companyId,
        string $companyTimezone,
        int $employeeId,
        string $date,
        ?string $shiftStart = null,
        ?string $shiftEnd = null,
    ): array {
        $log = DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('date', $date)
            ->orderBy('session_number')
            ->first();

        if ($log === null) {
            return $this->absentPayload($employeeId);
        }

        $checkInRaw = $log->check_in;
        $checkOutRaw = $log->check_out;
        $statusRaw = $log->status;
        $lateRaw = $log->late_minutes;
        $hoursRaw = $log->hours_worked;

        $checkIn = is_string($checkInRaw) ? $checkInRaw : null;
        $checkOut = is_string($checkOutRaw) ? $checkOutRaw : null;
        $status = is_string($statusRaw) ? $statusRaw : 'incomplete';
        $lateMinutes = is_numeric($lateRaw) ? (int) $lateRaw : 0;

        return [
            'employee_id' => $employeeId,
            'status' => $this->normalizeStatus($status, $lateMinutes),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'hours_worked' => is_numeric($hoursRaw) ? (float) $hoursRaw : null,
            'late_minutes' => $lateMinutes,
            'outside_shift' => $this->isOutsideShift($checkIn, $companyTimezone, $shiftStart, $shiftEnd),
        ];
    }

    /**
     * @return array{
     *     employee_id: int,
     *     status: string,
     *     check_in: null,
     *     check_out: null,
     *     hours_worked: null,
     *     late_minutes: int,
     *     outside_shift: bool
     * }
     */
    private function absentPayload(int $employeeId): array
    {
        return [
            'employee_id' => $employeeId,
            'status' => self::STATUS_ABSENT,
            'check_in' => null,
            'check_out' => null,
            'hours_worked' => null,
            'late_minutes' => 0,
            'outside_shift' => false,
        ];
    }

    private function normalizeStatus(string $status, int $lateMinutes): string
    {
        $base = match ($status) {
            'ontime' => self::STATUS_PRESENT,
            'late' => self::STATUS_LATE,
            'absent' => self::STATUS_ABSENT,
            'leave' => self::STATUS_LEAVE,
            'holiday' => self::STATUS_HOLIDAY,
            default => self::STATUS_INCOMPLETE,
        };

        if ($base === self::STATUS_PRESENT && $lateMinutes > 0) {
            return self::STATUS_LATE;
        }

        return $base;
    }

    private function isOutsideShift(
        ?string $checkIn,
        string $companyTimezone,
        ?string $shiftStart,
        ?string $shiftEnd,
    ): bool {
        if ($checkIn === null || $shiftStart === null || $shiftEnd === null) {
            return false;
        }

        $checkInLocal = Carbon::parse($checkIn)->setTimezone($companyTimezone);

        return $checkInLocal->format('H:i') < $shiftStart
            || $checkInLocal->format('H:i') > $shiftEnd;
    }
}
