<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Infrastructure\Services\EduAcademicYearService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduAcademicYearRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\UpdateEduAcademicYearRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des années scolaires EduManager — EDU-010 (issue #5826, EDU-003).
 *
 * CRUD tenant-scoped, direction uniquement (Policy EduAcademicYearPolicy).
 * Isolation fail-closed : ressource d'un autre tenant → 404.
 */
class EduAcademicYearController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduAcademicYearService $years)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduAcademicYear::class);

        $query = EduAcademicYear::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $years = $query->orderByDesc('start_date')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($years->items())->map(fn (EduAcademicYear $year): array => $this->payload($year)),
            'meta' => [
                'current_page' => $years->currentPage(),
                'per_page' => $years->perPage(),
                'total' => $years->total(),
            ],
        ]);
    }

    public function store(StoreEduAcademicYearRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduAcademicYear::class);

        $year = $this->years->createYear($actor, $request->validated());

        return response()->json(['data' => $this->payload($year)], 201);
    }

    public function show(Request $request, EduAcademicYear $year): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($year, $actor->company_id);
        $this->authorize('view', $year);

        return response()->json(['data' => $this->payload($year)]);
    }

    public function update(UpdateEduAcademicYearRequest $request, EduAcademicYear $year): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($year, $actor->company_id);
        $this->authorize('update', $year);

        $year->update($request->validated());

        return response()->json(['data' => $this->payload($year->refresh())]);
    }

    public function destroy(Request $request, EduAcademicYear $year): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($year, $actor->company_id);
        $this->authorize('delete', $year);

        $year->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduAcademicYear $year): array
    {
        return [
            'id' => (int) $year->getAttribute('id'),
            'name' => $year->name,
            'start_date' => $year->start_date?->toDateString(),
            'end_date' => $year->end_date?->toDateString(),
            'status' => $year->status,
            'notes' => $year->notes,
        ];
    }
}
