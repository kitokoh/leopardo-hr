<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendanceService;
use Illuminate\Validation\ValidationException;

/**
 * Cas d'usage : correction manuelle d'un log de pointage par un manager/RH.
 *
 * Extrait de `AttendanceController::update()` (issue #7962). Le contrôleur
 * conserve l'autorisation et le garde anti-existence cross-tenant ; les règles
 * temporelles et le recalcul vivent ici.
 */
class UpdateAttendanceLog
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    public function execute(AttendanceLog $attendanceLog, Employee $actor, array $validated): AttendanceLog
    {
        $effectiveCheckIn = array_key_exists('check_in', $validated)
            ? $validated['check_in']
            : $attendanceLog->check_in;
        $effectiveCheckOut = array_key_exists('check_out', $validated)
            ? $validated['check_out']
            : $attendanceLog->check_out;

        if ($effectiveCheckOut !== null && $effectiveCheckIn === null) {
            throw ValidationException::withMessages([
                'check_out' => [__('attendance.manual_checkout_requires_check_in')],
            ]);
        }

        if ($effectiveCheckIn !== null && $effectiveCheckOut !== null && $effectiveCheckOut <= $effectiveCheckIn) {
            throw ValidationException::withMessages([
                'check_out' => [__('attendance.checkout_after_checkin')],
            ]);
        }

        $attendanceLog->fill([
            'check_in' => $effectiveCheckIn,
            'check_out' => $effectiveCheckOut,
            'method' => 'manual',
            'work_type' => $validated['work_type'] ?? $attendanceLog->work_type ?? 'normal',
            'corrected_by' => $actor->id,
            'correction_note' => $validated['notes'] ?? $attendanceLog->correction_note,
        ]);

        return $this->attendanceService->recalculateLog($attendanceLog);
    }
}
