<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduAcademicYearRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Années scolaires (EDU-010, #5826). deny-by-default (EduAcademicYearPolicy).
 */
class EduAcademicYearController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduAcademicYear::class);

        $query = EduAcademicYear::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $years = $query->orderByDesc('start_date')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($years->items())->map(fn (EduAcademicYear $y): array => $this->payload($y)),
            'meta' => [
                'current_page' => $years->currentPage(),
                'last_page' => $years->lastPage(),
                'total' => $years->total(),
            ],
        ]);
    }

    public function store(StoreEduAcademicYearRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduAcademicYear::class);

        $year = EduAcademicYear::query()->create([
            'company_id' => $actor->company_id,
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'status' => $request->input('status', EduAcademicYear::STATUS_ACTIVE),
        ]);

        return response()->json(['data' => $this->payload($year->refresh())], 201);
    }

    public function update(StoreEduAcademicYearRequest $request, EduAcademicYear $year): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($year->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $year);

        $year->update($request->validated());

        return response()->json(['data' => $this->payload($year->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduAcademicYear $year): array
    {
        return [
            'id' => $year->id,
            'company_id' => $year->company_id,
            'code' => $year->code,
            'name' => $year->name,
            'start_date' => $year->start_date->toDateString(),
            'end_date' => $year->end_date->toDateString(),
            'status' => $year->status,
            'created_at' => $year->created_at?->toISOString(),
            'updated_at' => $year->updated_at?->toISOString(),
        ];
    }
}
