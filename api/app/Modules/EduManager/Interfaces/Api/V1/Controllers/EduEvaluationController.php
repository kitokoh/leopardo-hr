<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduEvaluation;
use App\Modules\EduManager\Domain\Models\EduGradeEntry;
use App\Modules\EduManager\Infrastructure\Services\EduGradeService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\CorrectEduGradeRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\EnterEduGradeRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduEvaluationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Évaluations & notes versionnées (EDU-010, #5826). deny-by-default
 * (EduEvaluationPolicy) : direction = tout ; enseignant = ses classes.
 */
class EduEvaluationController extends Controller
{
    public function __construct(private readonly EduGradeService $grades) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduEvaluation::class);

        $query = EduEvaluation::query()->where('company_id', $actor->company_id);

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->integer('class_id'));
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->integer('academic_year_id'));
        }

        $evaluations = $query->orderByDesc('created_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($evaluations->items())->map(fn (EduEvaluation $e): array => $this->payload($e)),
            'meta' => [
                'current_page' => $evaluations->currentPage(),
                'last_page' => $evaluations->lastPage(),
                'total' => $evaluations->total(),
            ],
        ]);
    }

    public function store(StoreEduEvaluationRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', [EduEvaluation::class, $request->integer('class_id')]);

        $evaluation = EduEvaluation::query()->create([
            'company_id' => $actor->company_id,
            'class_id' => $request->input('class_id'),
            'subject_id' => $request->input('subject_id'),
            'academic_year_id' => $request->input('academic_year_id'),
            'title' => $request->input('title'),
            'type' => $request->input('type', EduEvaluation::TYPE_EXAM),
            'coefficient' => $request->input('coefficient', 1),
            'max_score' => $request->input('max_score', 20),
            'status' => EduEvaluation::STATUS_DRAFT,
            'created_by' => $actor->id,
        ]);

        return response()->json(['data' => $this->payload($evaluation->refresh())], 201);
    }

    public function publish(Request $request, EduEvaluation $evaluation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($evaluation->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('publish', $evaluation);

        $evaluation = $this->grades->publish($actor, $evaluation);

        return response()->json(['data' => $this->payload($evaluation)]);
    }

    public function enterGrade(EnterEduGradeRequest $request, EduEvaluation $evaluation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($evaluation->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('publish', $evaluation);

        $entry = $this->grades->enter($actor, $evaluation, $request->validated());

        return response()->json(['data' => $this->gradePayload($entry)], 201);
    }

    public function grades(Request $request, EduEvaluation $evaluation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($evaluation->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $evaluation);

        $rows = EduGradeEntry::query()
            ->where('company_id', $actor->company_id)
            ->where('evaluation_id', $evaluation->id)
            ->orderBy('student_id')
            ->orderByDesc('version')
            ->get();

        // Dernière version par élève (append-only).
        $latestByStudent = [];
        foreach ($rows as $row) {
            $latestByStudent[(int) $row->student_id] = $row;
        }

        return response()->json([
            'data' => collect(array_values($latestByStudent))
                ->map(fn (EduGradeEntry $e): array => $this->gradePayload($e)),
        ]);
    }

    public function correctGrade(CorrectEduGradeRequest $request, EduGradeEntry $entry): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($entry->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('correct', $entry);

        $entry = $this->grades->correctPublished($actor, $entry, $request->validated());

        return response()->json(['data' => $this->gradePayload($entry)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduEvaluation $evaluation): array
    {
        return [
            'id' => $evaluation->id,
            'company_id' => $evaluation->company_id,
            'class_id' => $evaluation->class_id,
            'subject_id' => $evaluation->subject_id,
            'academic_year_id' => $evaluation->academic_year_id,
            'title' => $evaluation->title,
            'type' => $evaluation->type,
            'coefficient' => $evaluation->coefficient,
            'max_score' => $evaluation->max_score,
            'status' => $evaluation->status,
            'created_by' => $evaluation->created_by,
            'published_by' => $evaluation->published_by,
            'published_at' => $evaluation->published_at?->toISOString(),
            'created_at' => $evaluation->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gradePayload(EduGradeEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'company_id' => $entry->company_id,
            'evaluation_id' => $entry->evaluation_id,
            'student_id' => $entry->student_id,
            'score' => $entry->score,
            'status' => $entry->status,
            'comment' => $entry->comment,
            'version' => $entry->version,
            'entered_by' => $entry->entered_by,
            'correction_reason' => $entry->correction_reason,
            'corrected_by' => $entry->corrected_by,
            'corrected_at' => $entry->corrected_at?->toISOString(),
            'created_at' => $entry->created_at?->toISOString(),
        ];
    }
}
