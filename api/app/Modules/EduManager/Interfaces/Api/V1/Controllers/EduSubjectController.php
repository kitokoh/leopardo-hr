<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduSubjectRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\UpdateEduSubjectRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des matières — EDU-010 (issue #5826, EDU-003).
 */
class EduSubjectController extends Controller
{
    use ChecksEduSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduSubject::class);

        $query = EduSubject::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $subjects = $query->orderBy('name')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($subjects->items())->map(fn (EduSubject $subject): array => $this->payload($subject)),
            'meta' => [
                'current_page' => $subjects->currentPage(),
                'per_page' => $subjects->perPage(),
                'total' => $subjects->total(),
            ],
        ]);
    }

    public function store(StoreEduSubjectRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduSubject::class);

        /** @var EduSubject $subject */
        $subject = EduSubject::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]));

        return response()->json(['data' => $this->payload($subject)], 201);
    }

    public function show(Request $request, EduSubject $subject): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($subject, $actor->company_id);
        $this->authorize('view', $subject);

        return response()->json(['data' => $this->payload($subject)]);
    }

    public function update(UpdateEduSubjectRequest $request, EduSubject $subject): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($subject, $actor->company_id);
        $this->authorize('update', $subject);

        $subject->update($request->validated());

        return response()->json(['data' => $this->payload($subject->refresh())]);
    }

    public function destroy(Request $request, EduSubject $subject): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($subject, $actor->company_id);
        $this->authorize('delete', $subject);

        $subject->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduSubject $subject): array
    {
        return [
            'id' => (int) $subject->getAttribute('id'),
            'campus_id' => $subject->campus_id,
            'code' => $subject->code,
            'name' => $subject->name,
            'default_coefficient' => $subject->default_coefficient,
            'status' => $subject->status,
        ];
    }
}
