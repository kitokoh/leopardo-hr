<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduCampusRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\UpdateEduCampusRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des campus scolaires — EDU-010 (issue #5826, EDU-002).
 */
class EduCampusController extends Controller
{
    use ChecksEduSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduCampus::class);

        $query = EduCampus::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $campuses = $query->orderBy('name')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($campuses->items())->map(fn (EduCampus $campus): array => $this->payload($campus)),
            'meta' => [
                'current_page' => $campuses->currentPage(),
                'per_page' => $campuses->perPage(),
                'total' => $campuses->total(),
            ],
        ]);
    }

    public function store(StoreEduCampusRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduCampus::class);

        /** @var EduCampus $campus */
        $campus = EduCampus::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
        ]));

        return response()->json(['data' => $this->payload($campus)], 201);
    }

    public function show(Request $request, EduCampus $campus): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($campus, $actor->company_id);
        $this->authorize('view', $campus);

        return response()->json(['data' => $this->payload($campus)]);
    }

    public function update(UpdateEduCampusRequest $request, EduCampus $campus): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($campus, $actor->company_id);
        $this->authorize('update', $campus);

        $campus->update($request->validated());

        return response()->json(['data' => $this->payload($campus->refresh())]);
    }

    public function destroy(Request $request, EduCampus $campus): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($campus, $actor->company_id);
        $this->authorize('delete', $campus);

        $campus->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduCampus $campus): array
    {
        return [
            'id' => (int) $campus->getAttribute('id'),
            'code' => $campus->code,
            'name' => $campus->name,
            'address' => $campus->address,
            'timezone' => $campus->timezone,
            'status' => $campus->status,
        ];
    }
}
