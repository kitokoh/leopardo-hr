<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduGrade;
use Illuminate\Support\Facades\DB;

/**
 * Reporting scolaire (read models agrégés) — EDU-018 (issue #5834).
 *
 * - Présence : agrégats par classe/jour (présents, absents, retards,
 *   excusés) — AUCUN détail nominatif.
 * - Inscriptions : comptage des admissions par campus (pipeline).
 * - Résultats : moyenne par matière (notes publiées uniquement), par
 *   campus/année — aucun détail élève.
 * - Capacité : somme des capacités de classes + nb de classes par campus.
 *
 * Tous les agrégats sont tenant-scopés et direction-only (dashboard global
 * sans détail sensible). Queries agrégées (pas de N+1).
 */
final class EduReportService
{
    /**
     * Présence par classe sur une plage de dates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function presence(Employee $actor, ?int $classId, ?string $from, ?string $to): array
    {
        $query = EduAttendance::query()
            ->select('class_id', 'attendance_date', 'status', DB::raw('COUNT(*) as count'))
            ->where('company_id', $actor->company_id)
            ->groupBy('class_id', 'attendance_date', 'status')
            ->orderBy('attendance_date');

        if ($classId !== null) {
            $query->where('class_id', $classId);
        }
        if ($from !== null) {
            $query->where('attendance_date', '>=', $from);
        }
        if ($to !== null) {
            $query->where('attendance_date', '<=', $to);
        }

        return $query->get()
            ->groupBy(fn ($row): string => (string) $row->attendance_date)
            ->map(fn ($rows, string $date): array => [
                'date' => $date,
                'class_id' => $classId,
                'present' => (int) $rows->where('status', EduAttendance::STATUS_PRESENT)->sum('count'),
                'absent' => (int) $rows->where('status', EduAttendance::STATUS_ABSENT)->sum('count'),
                'late' => (int) $rows->where('status', EduAttendance::STATUS_LATE)->sum('count'),
                'excused' => (int) $rows->where('status', EduAttendance::STATUS_EXCUSED)->sum('count'),
            ])
            ->values()
            ->all();
    }

    /**
     * Inscriptions (admissions) par campus et statut.
     *
     * @return array<int, array<string, mixed>>
     */
    public function enrollment(Employee $actor, ?int $campusId, ?int $academicYearId): array
    {
        $query = EduAdmission::query()
            ->select('campus_id', 'status', DB::raw('COUNT(*) as count'))
            ->where('company_id', $actor->company_id)
            ->groupBy('campus_id', 'status')
            ->orderBy('campus_id');

        if ($campusId !== null) {
            $query->where('campus_id', $campusId);
        }
        if ($academicYearId !== null) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query->get()->map(fn ($row): array => [
            'campus_id' => $row->getAttribute('campus_id'),
            'status' => $row->getAttribute('status'),
            'count' => (int) $row->getAttribute('count'),
        ])->all();
    }

    /**
     * Moyennes par matière (notes publiées) — aucun détail élève.
     *
     * @return array<int, array<string, mixed>>
     */
    public function results(Employee $actor, ?int $campusId, ?int $academicYearId): array
    {
        $query = EduGrade::query()
            ->select(
                'edu_assessments.subject_id',
                DB::raw('AVG(edu_grades.score) as average'),
                DB::raw('COUNT(edu_grades.id) as grade_count')
            )
            ->join('edu_assessments', function ($join): void {
                $join->on('edu_assessments.id', '=', 'edu_grades.assessment_id')
                    ->on('edu_assessments.company_id', '=', 'edu_grades.company_id');
            })
            ->join('edu_classes', function ($join): void {
                $join->on('edu_classes.id', '=', 'edu_assessments.class_id')
                    ->on('edu_classes.company_id', '=', 'edu_assessments.company_id');
            })
            ->where('edu_grades.company_id', $actor->company_id)
            ->where('edu_grades.status', '!=', EduGrade::STATUS_DRAFT)
            ->groupBy('edu_assessments.subject_id')
            ->orderBy('edu_assessments.subject_id');

        if ($campusId !== null) {
            $query->where('edu_classes.campus_id', $campusId);
        }
        if ($academicYearId !== null) {
            $query->where('edu_assessments.academic_year_id', $academicYearId);
        }

        return $query->get()->map(fn ($row): array => [
            'subject_id' => (int) $row->getAttribute('subject_id'),
            'average' => (string) round((float) $row->getAttribute('average'), 2),
            'grade_count' => (int) $row->getAttribute('grade_count'),
        ])->all();
    }

    /**
     * Capacité par campus (somme des capacités + nb de classes).
     *
     * @return array<int, array<string, mixed>>
     */
    public function capacity(Employee $actor, ?int $campusId): array
    {
        $query = EduClass::query()
            ->select('campus_id', DB::raw('COUNT(*) as class_count'), DB::raw('COALESCE(SUM(capacity), 0) as total_capacity'))
            ->where('company_id', $actor->company_id)
            ->where('status', EduClass::STATUS_ACTIVE)
            ->groupBy('campus_id')
            ->orderBy('campus_id');

        if ($campusId !== null) {
            $query->where('campus_id', $campusId);
        }

        return $query->get()->map(fn ($row): array => [
            'campus_id' => (int) $row->getAttribute('campus_id'),
            'class_count' => (int) $row->getAttribute('class_count'),
            'total_capacity' => (int) $row->getAttribute('total_capacity'),
        ])->all();
    }
}
