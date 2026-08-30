<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduClassRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Classes (EDU-010, #5826). deny-by-default (EduClassPolicy).
 */
class EduClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduClass::class);

        $query = EduClass::query()->where('company_id', $actor->company_id);

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->integer('academic_year_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $classes = $query->orderBy('name')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($classes->items())->map(fn (EduClass $c): array => $this->payload($c)),
            'meta' => [
                'current_page' => $classes->currentPage(),
                'last_page' => $classes->lastPage(),
                'total' => $classes->total(),
            ],
        ]);
    }

    public function store(StoreEduClassRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduClass::class);

        $class = EduClass::query()->create([
            'company_id' => $actor->company_id,
            'campus_id' => $request->input('campus_id'),
            'academic_year_id' => $request->input('academic_year_id'),
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'grade_level' => $request->input('grade_level'),
            'capacity' => $request->input('capacity'),
            'status' => $request->input('status', EduClass::STATUS_ACTIVE),
        ]);

        return response()->json(['data' => $this->payload($class->refresh())], 201);
    }

    public function update(StoreEduClassRequest $request, EduClass $class): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($class->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $class);

        $class->update($request->validated());

        return response()->json(['data' => $this->payload($class->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduClass $class): array
    {
        return [
            'id' => $class->id,
            'company_id' => $class->company_id,
            'campus_id' => $class->campus_id,
            'academic_year_id' => $class->academic_year_id,
            'code' => $class->code,
            'name' => $class->name,
            'grade_level' => $class->grade_level,
            'capacity' => $class->capacity,
            'status' => $class->status,
            'created_at' => $class->created_at?->toISOString(),
            'updated_at' => $class->updated_at?->toISOString(),
        ];
    }
}
