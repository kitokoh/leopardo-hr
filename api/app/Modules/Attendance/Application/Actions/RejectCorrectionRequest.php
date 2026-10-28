<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Attendance\Infrastructure\Services\AttendancePeriodClosureService;
use Illuminate\Validation\ValidationException;

/**
 * Cas d'usage : rejet d'une demande de correction de pointage (#5267).
 *
 * Extrait de `AttendanceController::rejectCorrection()` (issue #7962).
 * Aucune écriture sur le log : seule la demande change d'état, et la décision
 * est auditée.
 */
class RejectCorrectionRequest
{
    public function __construct(
        private readonly AttendancePeriodClosureService $periodClosures,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(AttendanceCorrectionRequest $correction, Employee $actor): AttendanceCorrectionRequest
    {
        // Issue #5267 : une période clôturée verrouille les décisions aussi.
        $this->periodClosures->assertPeriodOpen((string) $correction->company_id, $correction->date);

        if ($correction->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => [__('attendance.correction_already_processed')],
            ]);
        }

        $correction->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        // Issue #5267 : décision tracée (audit trail).
        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'attendance_correction_rejected',
            'auditable_type' => $correction->getMorphClass(),
            'auditable_id' => $correction->id,
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'rejected'],
        ]);

        $correction->load(['employee', 'attendanceLog']);

        return $correction;
    }
}
