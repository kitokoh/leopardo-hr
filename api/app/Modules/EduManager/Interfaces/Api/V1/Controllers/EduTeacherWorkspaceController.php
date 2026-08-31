<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduClassEnrollment;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace enseignant — EDU-012 (issue #5828).
 *
 * Un enseignant consulte SES classes (référentes + affectations
 * edu_teacher_subjects, via EduAccess::teacherClassIds) — jamais une autre
 * classe ; il voit les effectifs et les notes en attente de saisie, et
 * soumet pour validation (les écritures restent bornées par les Policies
 * EduAttendance/EduGrade existantes, validation serveur systématique).
 */
class EduTeacherWorkspaceController extends Controller
{
    use ChecksEduSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(EduAccess::isAdmin($actor) || EduAccess::isTeacher($actor), 403, 'EDU_TEACHER_ONLY');

        $companyId = $actor->company_id;
        $classIds = EduAccess::teacherClassIds($actor);

        $classes = EduClass::query()
            ->with('academicYear:id,name')
            ->with('campus:id,name')
            ->where('company_id', $companyId)
            ->whereIn('id', $classIds)
            ->orderBy('name')
            ->get();

        $pendingGrades = $classIds->isNotEmpty()
            ? EduGrade::query()
                ->where('company_id', $companyId)
                ->whereIn('status', [EduGrade::STATUS_DRAFT])
                ->whereHas('assessment', fn (Builder $query): Builder => $query->whereIn('class_id', $classIds))
                ->count()
            : 0;

        return response()->json([
            'data' => [
                'role' => EduAccess::isAdmin($actor) ? 'admin' : 'teacher',
                'classes' => $classes->map(function (EduClass $class) use ($companyId): array {
                    $enrolled = EduClassEnrollment::query()
                        ->where('company_id', $companyId)
                        ->where('class_id', $class->getAttribute('id'))
                        ->where('status', EduClassEnrollment::STATUS_ACTIVE)
                        ->count();

                    return [
                        'id' => (int) $class->getAttribute('id'),
                        'code' => $class->code,
                        'name' => $class->name,
                        'level' => $class->level,
                        'academic_year' => $class->relationLoaded('academicYear') && $class->academicYear !== null
                            ? ['id' => (int) $class->academicYear->getAttribute('id'), 'name' => $class->academicYear->name]
                            : null,
                        'campus' => $class->relationLoaded('campus') && $class->campus !== null
                            ? ['id' => (int) $class->campus->getAttribute('id'), 'name' => $class->campus->name]
                            : null,
                        'enrolled_students' => $enrolled,
                    ];
                })->values()->all(),
                'summary' => [
                    'classes_count' => $classes->count(),
                    'pending_grade_submissions' => $pendingGrades,
                ],
            ],
        ]);
    }
}
