<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduAttendanceCorrection;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduTeacherSubject;
use Illuminate\Database\Eloquent\Builder;

/**
 * Règles métier de la présence scolaire — EDU-005 (issue #5821).
 *
 * - Saisie idempotente : UNIQUE (class_id, student_id, attendance_date) par
 *   tenant → firstOrCreate (rejeu sûr).
 * - Correction versionnée : jamais d'UPDATE silencieux — chaque correction
 *   écrit une ligne `edu_attendance_corrections` puis met à jour le statut
 *   courant (audit trail complet).
 * - Périmètre enseignant : un enseignant ne voit que les classes qu'il
 *   enseigne (edu_teacher_subjects) ou dont il est référent
 *   (edu_classes.teacher_id) — helper scope ci-dessous.
 */
final class EduAttendanceService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Employee $actor, EduClass $class, array $data): EduAttendance
    {
        abort_if($class->company_id !== $actor->company_id, 404);

        $payload = array_merge($data, [
            'company_id' => $actor->company_id,
            'class_id' => (int) $class->getAttribute('id'),
            'recorded_by' => $actor->id,
        ]);

        /** @var EduAttendance $attendance */
        $attendance = EduAttendance::query()->firstOrCreate(
            [
                'company_id' => $actor->company_id,
                'class_id' => (int) $class->getAttribute('id'),
                'student_id' => (int) $data['student_id'],
                'attendance_date' => $data['attendance_date'],
            ],
            $payload
        );

        return $attendance;
    }

    /**
     * Correction versionnée d'une présence (jamais d'UPDATE silencieux).
     *
     * @param  array<string, mixed>  $data
     */
    public function correct(Employee $actor, EduAttendance $attendance, array $data): EduAttendance
    {
        abort_if($attendance->company_id !== $actor->company_id, 404);
        abort_if(! isset($data['status']), 422, 'VALIDATION_FAILED');

        $newStatus = (string) $data['status'];
        abort_if(! in_array($newStatus, EduAttendance::STATUSES, true), 422, 'EDU_ATTENDANCE_STATUS');

        $previousStatus = (string) $attendance->status;

        if ($previousStatus === $newStatus) {
            return $attendance;
        }

        EduAttendanceCorrection::query()->create([
            'company_id' => $actor->company_id,
            'attendance_id' => (int) $attendance->getAttribute('id'),
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'reason' => $data['reason'] ?? null,
            'corrected_by' => $actor->id,
        ]);

        $attendance->update(['status' => $newStatus]);

        return $attendance->refresh();
    }

    /**
     * Scope des classes d'un enseignant (classes référentes + enseignées).
     *
     * @return Builder<EduClass>
     */
    public function teacherClassQuery(Employee $actor): Builder
    {
        $classIds = EduTeacherSubject::query()
            ->where('company_id', $actor->company_id)
            ->where('teacher_id', $actor->id)
            ->pluck('class_id');

        /** @var Builder<EduClass> $query */
        $query = EduClass::query()
            ->where('company_id', $actor->company_id)
            ->where(function (Builder $builder) use ($actor, $classIds): void {
                $builder->where('teacher_id', $actor->id)
                    ->orWhereIn('id', $classIds);
            });

        return $query;
    }
}
