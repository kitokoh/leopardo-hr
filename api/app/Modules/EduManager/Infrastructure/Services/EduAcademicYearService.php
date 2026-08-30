<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduTeacherSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Règles métier des années scolaires, classes et enseignants — EDU-003
 * (issue #5819).
 *
 * - Période cohérente : `start_date` strictement avant `end_date` (doublé
 *   par le CHECK `edu_academic_years_period_check`) et aucun chevauchement
 *   avec une année ACTIVE du même tenant (EDU_ACADEMIC_YEAR_OVERLAP).
 * - Enseignant du même tenant : `teacher_id` doit référencer un employé RH
 *   dont `company_id` == tenant courant (EMPLOYEE_OUTSIDE_TENANT sinon).
 * - Classe : code unique par (tenant, année) porté par la DB ; campus et
 *   année du même tenant garantis par les FK composites.
 * - Affectation enseignant→matière idempotente (firstOrCreate, UNIQUE
 *   tenant+classe+matière+enseignant) ; le retrait passe par status=inactive.
 */
final class EduAcademicYearService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createYear(Employee $actor, array $data): EduAcademicYear
    {
        /** @var Carbon $start */
        $start = Carbon::parse($data['start_date']);
        /** @var Carbon $end */
        $end = Carbon::parse($data['end_date']);

        abort_if(! $start->lt($end), 422, 'EDU_ACADEMIC_YEAR_PERIOD');

        $overlap = EduAcademicYear::query()
            ->where('company_id', $actor->company_id)
            ->where('status', EduAcademicYear::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($start, $end): void {
                $query->whereDate('start_date', '<=', $end->toDateString())
                    ->whereDate('end_date', '>=', $start->toDateString());
            })
            ->exists();

        abort_if($overlap, 422, 'EDU_ACADEMIC_YEAR_OVERLAP');

        /** @var EduAcademicYear $year */
        $year = EduAcademicYear::query()->create(array_merge($data, [
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]));

        return $year;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createClass(Employee $actor, array $data): EduClass
    {
        if (isset($data['teacher_id']) && $data['teacher_id'] !== null) {
            $this->assertTeacherSameTenant($actor, (int) $data['teacher_id']);
        }

        /** @var EduClass $class */
        $class = EduClass::query()->create(array_merge($data, [
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]));

        return $class;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assignTeacher(Employee $actor, array $data): EduTeacherSubject
    {
        $this->assertTeacherSameTenant($actor, (int) $data['teacher_id']);

        /** @var EduTeacherSubject $assignment */
        $assignment = EduTeacherSubject::query()->firstOrCreate(array_merge($data, [
            'company_id' => $actor->company_id,
        ]), [
            'status' => EduTeacherSubject::STATUS_ACTIVE,
            'created_by' => $actor->id,
        ]);

        return $assignment;
    }

    private function assertTeacherSameTenant(Employee $actor, int $employeeId): void
    {
        $teacher = Employee::query()->find($employeeId);

        abort_if($teacher === null || $teacher->company_id !== $actor->company_id, 422, 'EMPLOYEE_OUTSIDE_TENANT');
    }
}
