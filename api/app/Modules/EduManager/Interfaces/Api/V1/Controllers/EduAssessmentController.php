<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Infrastructure\Services\EduGradeService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\CorrectEduGradeRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduAssessmentRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduGradeRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\UpdateEduAssessmentRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des évaluations et notes — EDU-010 (issue #5826, EDU-007).
 *
 * Notes bornées [0, max_score], corrections VERSIONNÉES (journal), barème
 * par évaluation. Confidentialité : enseignant = ses classes (Policy).
 */
class EduAssessmentController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduGradeService $grades)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduAssessment::class);

        $query = EduAssessment::query()->with(['class:id,code,name', 'subject:id,code,name'])
            ->where('company_id', $actor->company_id);

        if ($request->filled('class_id')) {
            $query->where('class_id', (int) $request->input('class_id'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', (int) $request->input('subject_id'));
        }

        $assessments = $query->orderByDesc('assessment_date')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($assessments->items())->map(fn (EduAssessment $assessment): array => $this->payload($assessment)),
            'meta' => [
                'current_page' => $assessments->currentPage(),
                'per_page' => $assessments->perPage(),
                'total' => $assessments->total(),
            ],
        ]);
    }

    public function store(StoreEduAssessmentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduAssessment::class);

        /** @var EduAssessment $assessment */
        $assessment = EduAssessment::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]));

        return response()->json(['data' => $this->payload($assessment)], 201);
    }

    public function show(Request $request, EduAssessment $assessment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($assessment, $actor->company_id);
        $this->authorize('view', $assessment);

        return response()->json(['data' => $this->payload($assessment)]);
    }

    public function update(UpdateEduAssessmentRequest $request, EduAssessment $assessment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($assessment, $actor->company_id);
        $this->authorize('update', $assessment);

        $assessment->update($request->validated());

        return response()->json(['data' => $this->payload($assessment->refresh())]);
    }

    public function destroy(Request $request, EduAssessment $assessment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($assessment, $actor->company_id);
        $this->authorize('delete', $assessment);

        $assessment->delete();

        return response()->json(null, 204);
    }

    public function grade(StoreEduGradeRequest $request, EduAssessment $assessment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($assessment, $actor->company_id);
        $this->authorize('update', $assessment);

        $grade = $this->grades->grade($actor, $assessment, $request->validated());

        return response()->json(['data' => $this->gradePayload($grade)], 201);
    }

    public function publishGrade(Request $request, EduGrade $grade): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($grade, $actor->company_id);
        $this->authorize('update', $grade);

        $published = $this->grades->publish($actor, $grade);

        return response()->json(['data' => $this->gradePayload($published)]);
    }

    public function correctGrade(CorrectEduGradeRequest $request, EduGrade $grade): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($grade, $actor->company_id);
        $this->authorize('correct', $grade);

        $corrected = $this->grades->correct($actor, $grade, $request->validated());

        return response()->json(['data' => $this->gradePayload($corrected)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduAssessment $assessment): array
    {
        return [
            'id' => (int) $assessment->getAttribute('id'),
            'class_id' => $assessment->class_id,
            'subject_id' => $assessment->subject_id,
            'academic_year_id' => $assessment->academic_year_id,
            'title' => $assessment->title,
            'type' => $assessment->type,
            'coefficient' => $assessment->coefficient,
            'max_score' => $assessment->max_score,
            'assessment_date' => $assessment->assessment_date?->toDateString(),
            'published_at' => $assessment->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gradePayload(EduGrade $grade): array
    {
        return [
            'id' => (int) $grade->getAttribute('id'),
            'assessment_id' => $grade->assessment_id,
            'student_id' => $grade->student_id,
            'score' => $grade->score,
            'comment' => $grade->comment,
            'status' => $grade->status,
            'version' => (int) $grade->version,
            'published_at' => $grade->published_at?->toIso8601String(),
        ];
    }
}
