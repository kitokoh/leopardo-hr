<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduReportCardLine;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Infrastructure\Services\EduReportCardService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\GenerateEduReportCardRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des bulletins — EDU-010 (issue #5826, EDU-008).
 *
 * Génération (read model recalculable), validation et publication par la
 * direction (Policy EduReportCardPolicy).
 */
class EduReportCardController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduReportCardService $cards)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduReportCard::class);

        $query = EduReportCard::query()->with('student:id,student_number,display_name')
            ->where('company_id', $actor->company_id);

        if ($request->filled('student_id')) {
            $query->where('student_id', (int) $request->input('student_id'));
        }

        if ($request->filled('period')) {
            $query->where('period', $request->input('period'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $cards = $query->orderByDesc('created_at')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($cards->items())->map(fn (EduReportCard $card): array => $this->payload($card)),
            'meta' => [
                'current_page' => $cards->currentPage(),
                'per_page' => $cards->perPage(),
                'total' => $cards->total(),
            ],
        ]);
    }

    public function generate(GenerateEduReportCardRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduReportCard::class);

        /** @var EduStudent $student */
        $student = EduStudent::query()->findOrFail((int) $request->input('student_id'));
        $this->assertSameTenant($student, $actor->company_id);

        $card = $this->cards->generate($actor, $student, $request->validated());

        return response()->json(['data' => $this->payload($card->load('student:id,student_number,display_name'), true)], 201);
    }

    public function show(Request $request, EduReportCard $card): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($card, $actor->company_id);
        $this->authorize('view', $card);

        return response()->json(['data' => $this->payload($card->load('lines.subject:id,code,name'), true)]);
    }

    public function validate(Request $request, EduReportCard $card): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($card, $actor->company_id);
        $this->authorize('validate', $card);

        $validated = $this->cards->validate($actor, $card);

        return response()->json(['data' => $this->payload($validated)]);
    }

    public function publish(Request $request, EduReportCard $card): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($card, $actor->company_id);
        $this->authorize('publish', $card);

        $published = $this->cards->publish($actor, $card);

        return response()->json(['data' => $this->payload($published)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduReportCard $card, bool $withLines = false): array
    {
        $payload = [
            'id' => (int) $card->getAttribute('id'),
            'student_id' => $card->student_id,
            'student' => $card->relationLoaded('student') && $card->student !== null
                ? [
                    'id' => (int) $card->student->getAttribute('id'),
                    'student_number' => $card->student->student_number,
                    'display_name' => $card->student->display_name,
                ]
                : null,
            'academic_year_id' => $card->academic_year_id,
            'period' => $card->period,
            'status' => $card->status,
            'generated_at' => $card->generated_at?->toIso8601String(),
            'validated_at' => $card->validated_at?->toIso8601String(),
            'published_at' => $card->published_at?->toIso8601String(),
        ];

        if ($withLines && $card->relationLoaded('lines')) {
            $payload['lines'] = $card->lines->map(fn (EduReportCardLine $line): array => [
                'subject_id' => $line->subject_id,
                'subject_code' => $line->subject?->code,
                'subject_name' => $line->subject?->name,
                'average' => $line->average,
                'coefficient' => $line->coefficient,
                'assessment_count' => (int) $line->assessment_count,
            ])->values()->all();
        }

        return $payload;
    }
}
