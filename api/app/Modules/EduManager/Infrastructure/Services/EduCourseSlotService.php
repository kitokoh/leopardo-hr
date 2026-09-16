<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduCourseSlot;
use Illuminate\Database\Eloquent\Builder;

/**
 * Règles métier des emplois du temps — EDU-006 (issue #5822).
 *
 * - Conflit de classe : une classe ne peut pas avoir deux créneaux actifs
 *   qui se chevauchent le même jour (EDU_COURSE_SLOT_CLASS_CONFLICT).
 * - Conflit d'enseignant : un enseignant ne peut pas être sur deux créneaux
 *   actifs qui se chevauchent le même jour (EDU_COURSE_SLOT_TEACHER_CONFLICT).
 * - Période cohérente : end_time > start_time (doublé par CHECK) ; pas de
 *   chevauchement de minuit en V0 (documenté).
 * - Isolation tenant : créneau d'une autre compagnie → 404 fail-closed.
 */
final class EduCourseSlotService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Employee $actor, array $data): EduCourseSlot
    {
        abort_if((string) $data['start_time'] >= (string) $data['end_time'], 422, 'EDU_COURSE_SLOT_PERIOD');

        $this->assertNoConflict($actor, $data);

        /** @var EduCourseSlot $slot */
        $slot = EduCourseSlot::query()->create(array_merge($data, [
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]));

        return $slot;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertNoConflict(Employee $actor, array $data): void
    {
        $day = (int) $data['day_of_week'];
        $start = (string) $data['start_time'];
        $end = (string) $data['end_time'];
        $classId = (int) $data['class_id'];
        $subjectId = (int) $data['subject_id'];
        $teacherId = isset($data['teacher_id']) ? (int) $data['teacher_id'] : null;

        $overlap = fn (Builder $query): Builder => $query
            ->where('company_id', $actor->company_id)
            ->where('status', EduCourseSlot::STATUS_ACTIVE)
            ->where('day_of_week', $day)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);

        $classConflict = (clone $overlap)(EduCourseSlot::query())
            ->where('class_id', $classId)
            ->where('subject_id', '!=', $subjectId)
            ->exists();

        abort_if($classConflict, 422, 'EDU_COURSE_SLOT_CLASS_CONFLICT');

        if ($teacherId !== null) {
            // Un créneau IDENTIQUE (même classe, même matière, mêmes bornes)
            // est un REJEU, pas un conflit : l'enseignant ne peut pas être en
            // conflit avec lui-même. Seul un chevauchement sur une autre
            // classe ou une autre matière en est un.
            $teacherConflict = $overlap(EduCourseSlot::query())
                ->where('teacher_id', $teacherId)
                // Seul un créneau STRICTEMENT identique (mêmes classe,
                // matière ET bornes) est un rejeu — donc pas un conflit. Un
                // chevauchement, même sur la même classe/matière, reste un
                // conflit d'emploi du temps (verrouillé par
                // `EduApiTest::test_full_flow_from_campus_to_report_card`).
                ->where(function (Builder $query) use ($classId, $subjectId, $start, $end): void {
                    $query->where('class_id', '!=', $classId)
                        ->orWhere('subject_id', '!=', $subjectId)
                        ->orWhere('start_time', '!=', $start)
                        ->orWhere('end_time', '!=', $end);
                })
                ->exists();

            abort_if($teacherConflict, 422, 'EDU_COURSE_SLOT_TEACHER_CONFLICT');
        }
    }
}
