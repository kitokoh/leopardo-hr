<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendancePeriodClosureService;
use App\Modules\Attendance\Infrastructure\Services\AttendanceService;
use Illuminate\Validation\ValidationException;

/**
 * Cas d'usage : approbation d'une demande de correction de pointage (#5267).
 *
 * Extrait de `AttendanceController::approveCorrection()` (issue #7962,
 * principe « 1 action = 1 use case »).
 *
 * 1. Une période clôturée verrouille la décision.
 * 2. Le log ciblé est mis à jour, ou créé s'il n'existe pas encore (session
 *    suivante de la journée).
 * 3. Les heures sont recalculées par la source de vérité unique.
 * 4. La demande passe à `applied` et la décision est auditée.
 */
class ApproveCorrectionRequest
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly AttendancePeriodClosureService $periodClosures,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(AttendanceCorrectionRequest $correction, Employee $actor): AttendanceLog
    {
        // Issue #5267 : une période clôturée verrouille les décisions aussi.
        $this->periodClosures->assertPeriodOpen((string) $correction->company_id, $correction->date);

        if ($correction->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => [__('attendance.correction_already_processed')],
            ]);
        }

        $employee = Employee::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($correction->employee_id);

        $log = $correction->attendanceLog;

        if (! $log) {
            $sessionNumber = ((int) AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', $correction->date)
                ->max('session_number')) + 1;

            $log = new AttendanceLog([
                'company_id' => $actor->company_id,
                'employee_id' => $employee->id,
                'schedule_id' => $employee->schedule_id,
                'date' => $correction->date,
                'session_number' => $sessionNumber,
                'method' => 'manual',
                'work_type' => 'normal',
            ]);
        }

        $log->fill([
            'check_in' => $correction->requested_check_in,
            'check_out' => $correction->requested_check_out,
            'method' => 'manual',
            'corrected_by' => $actor->id,
            'correction_note' => $correction->reason,
        ]);

        $log = $this->attendanceService->recalculateLog($log);

        $correction->forceFill([
            'attendance_log_id' => $log->id,
            'status' => 'applied',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        // Issue #5267 : décision tracée (audit trail).
        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'attendance_correction_approved',
            'auditable_type' => $correction->getMorphClass(),
            'auditable_id' => $correction->id,
            'old_values' => ['status' => 'pending'],
            'new_values' => [
                'status' => 'applied',
                'attendance_log_id' => $log->id,
                'check_in' => $log->check_in?->toIso8601String(),
                'check_out' => $log->check_out?->toIso8601String(),
            ],
        ]);

        $correction->load(['employee', 'attendanceLog']);

        return $log;
    }
}
