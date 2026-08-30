<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Exceptions\EduSolutionInactiveException;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Infrastructure\Services\EduGradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Interface enseignant — EDU-012 (issue #5828).
 *
 * Périmètre STRICTEMENT borné aux classes de l'enseignant (EduAccess) :
 * un enseignant ne voit ni ne modifie une autre classe (404, jamais 403).
 * Saisie des notes déléguée à `EduGradeService` (validation serveur,
 * barème, versionnement) ; soumission pour validation = publication
 * (`publish`), réservée à la classe de l'enseignant.
 */
class EduTeacherInterfaceController extends Controller
{
    public function __construct(private readonly EduGradeService $grades)
    {
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('edumanager', currentCompany())) {
            throw new EduSolutionInactiveException;
        }
    }

    /**
     * GET /edu-manager/teacher/classes — classes enseignées par l'acteur.
     */
    public function classes(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        $classIds = EduAccess::teacherClassIds($actor);

        $classes = EduClass::query()
            ->whereIn('id', $classIds)
            ->orderBy('name')
            ->get()
            ->map(fn (EduClass $class): array => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'campus_id' => $class->campus_id,
                'academic_year_id' => $class->academic_year_id,
            ]);

        return response()->json(['data' => $classes]);
    }

    /**
     * GET /edu-manager/teacher/classes/{class}/students — élèves d'UNE de
     * ses classes (404 si la classe n'est pas enseignée par l'acteur).
     *
     * Le schéma ne porte pas de table d'inscription classe↔élève : le
     * registre est dérivé des présences (edu_attendances.class_id) et des
     * notes (edu_grades via les évaluations de la classe) — sources métier
     * de la classe réelle, dédupliquées et ordonnées.
     */
    public function students(Request $request, EduClass $class): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if (! EduAccess::canManageClass($actor, $class)) {
            abort(404);
        }

        $assessmentIds = EduAssessment::query()
            ->where('class_id', $class->id)
            ->pluck('id');

        $studentIds = EduAttendance::query()
            ->where('class_id', $class->id)
            ->pluck('student_id')
            ->merge(
                EduGrade::query()
                    ->whereIn('assessment_id', $assessmentIds)
                    ->pluck('student_id')
            )
            ->unique()
            ->values();

        $students = EduStudent::query()
            ->whereIn('id', $studentIds)
            ->orderBy('display_name')
            ->get()
            ->map(fn (EduStudent $student): array => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'display_name' => $student->display_name,
                'status' => $student->status,
            ]);

        return response()->json(['data' => $students]);
    }

    /**
     * GET /edu-manager/teacher/classes/{class}/assessments — évaluations
     * d'une de ses classes (pour saisie des notes).
     */
    public function assessments(Request $request, EduClass $class): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if (! EduAccess::canManageClass($actor, $class)) {
            abort(404);
        }

        $assessments = EduAssessment::query()
            ->where('class_id', $class->id)
            ->orderByDesc('assessment_date')
            ->get()
            ->map(fn (EduAssessment $assessment): array => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'type' => $assessment->type,
                'coefficient' => $assessment->coefficient,
                'max_score' => $assessment->max_score,
                'assessment_date' => $assessment->assessment_date?->toDateString(),
            ]);

        return response()->json(['data' => $assessments]);
    }

    /**
     * POST /edu-manager/teacher/grades/{grade}/submit — soumission d'une
     * note pour validation (enseignant de la classe uniquement).
     */
    public function submitGrade(Request $request, EduGrade $grade): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($grade->company_id !== $actor->company_id) {
            abort(404);
        }

        $assessment = $grade->assessment;

        if (! $assessment instanceof EduAssessment
            || ! EduAccess::canManageClass($actor, $assessment->class)) {
            abort(404);
        }

        $published = $this->grades->publish($actor, $grade);

        return response()->json([
            'data' => [
                'id' => $published->id,
                'assessment_id' => $published->assessment_id,
                'student_id' => $published->student_id,
                'score' => $published->score,
                'status' => $published->status,
                'published_at' => $published->published_at?->toIso8601String(),
            ],
        ]);
    }
}
