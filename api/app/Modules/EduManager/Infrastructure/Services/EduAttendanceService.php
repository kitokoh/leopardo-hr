<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAttendanceRecord;
use Illuminate\Support\Facades\DB;

/**
 * Présence scolaire — EDU-005 (issue #5821).
 *
 * - Enregistrement IDEMPOTENT : UNIQUE (company_id, student_id, class_id,
 *   session_date, subject_id) — un rejeu met à jour la même ligne, jamais
 *   de doublon.
 * - Corrections VERSIONNÉES et AUDITÉES : la ligne courante porte
 *   version/previous_status/correction_reason, et chaque correction est
 *   journalisée en append-only dans `edu_attendance_corrections`.
 * - Zéro fuite tenant : toutes les écritures sont scopées company_id.
 */
final class EduAttendanceService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Employee $actor, array $data): EduAttendanceRecord
    {
        $companyId = (string) $actor->company_id;
        $classId = isset($data['class_id']) && is_numeric($data['class_id']) ? (int) $data['class_id'] : 0;
        $studentId = isset($data['student_id']) && is_numeric($data['student_id']) ? (int) $data['student_id'] : 0;
        $subjectId = isset($data['subject_id']) && is_numeric($data['subject_id']) ? (int) $data['subject_id'] : null;
        $sessionDate = is_string($data['session_date'] ?? null) ? $data['session_date'] : now()->toDateString();

        $this->assertClassInTenant($companyId, $classId);
        $this->assertStudentInTenant($companyId, $studentId);

        $status = is_string($data['status'] ?? null) ? $data['status'] : EduAttendanceRecord::STATUS_PRESENT;
        $reason = $data['reason'] ?? null;

        // Un absent/late sans motif n'est pas accepté silencieusement.
        if (in_array($status, [EduAttendanceRecord::STATUS_ABSENT, EduAttendanceRecord::STATUS_LATE], true)
            && (! is_string($reason) || trim($reason) === '')) {
            abort(422, 'ATTENDANCE_REASON_REQUIRED');
        }

        /** @var EduAttendanceRecord|null $existing */
        $existing = EduAttendanceRecord::query()
            ->where('company_id', $companyId)
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('session_date', $sessionDate)
            ->where('subject_id', $subjectId)
            ->first();

        if ($existing instanceof EduAttendanceRecord) {
            $justified = isset($data['justified']) && is_bool($data['justified']) ? $data['justified'] : (bool) ($data['justified'] ?? false);

            return $this->correct($actor, $existing, $status, is_string($reason) ? $reason : null, $justified, 'Rejeu/mise à jour de la présence');
        }

        return EduAttendanceRecord::query()->create([
            'company_id' => $companyId,
            'class_id' => $classId,
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'session_date' => $sessionDate,
            'session_label' => $data['session_label'] ?? null,
            'status' => $status,
            'reason' => is_string($reason) ? $reason : null,
            'justified' => (bool) ($data['justified'] ?? false),
            'recorded_by' => $actor->id,
            'version' => 1,
        ]);
    }

    /**
     * Correction versionnée + audit append-only.
     */
    public function correct(Employee $actor, EduAttendanceRecord $record, string $newStatus, ?string $reason, bool $justified, ?string $correctionReason = null): EduAttendanceRecord
    {
        if ($record->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $reasonText = $correctionReason ?? 'Correction de présence';

        if ($newStatus === $record->status && $justified === (bool) $record->justified) {
            return $record->refresh();
        }

        DB::table('edu_attendance_corrections')->insert([
            'company_id' => $record->company_id,
            'record_id' => $record->id,
            'from_status' => $record->status,
            'to_status' => $newStatus,
            'reason' => $reasonText,
            'corrected_by' => $actor->id,
            'corrected_at' => now(),
        ]);

        $record->forceFill([
            'status' => $newStatus,
            'reason' => is_string($reason) ? $reason : $record->reason,
            'justified' => $justified,
            'version' => $record->version + 1,
            'previous_status' => $record->status,
            'correction_reason' => $reasonText,
            'corrected_by' => $actor->id,
            'corrected_at' => now(),
        ])->save();

        return $record->refresh();
    }

    private function assertClassInTenant(string $companyId, int $classId): void
    {
        $exists = DB::table('edu_classes')
            ->where('company_id', $companyId)
            ->where('id', $classId)
            ->exists();

        abort_if(! $exists, 422, 'CLASS_OUTSIDE_TENANT');
    }

    private function assertStudentInTenant(string $companyId, int $studentId): void
    {
        // Table livrée par EDU-002 (PR #5974) : validation seulement si elle existe.
        if (! DB::getSchemaBuilder()->hasTable('edu_students')) {
            return;
        }

        $exists = DB::table('edu_students')
            ->where('company_id', $companyId)
            ->where('id', $studentId)
            ->exists();

        abort_if(! $exists, 422, 'STUDENT_OUTSIDE_TENANT');
    }
}
