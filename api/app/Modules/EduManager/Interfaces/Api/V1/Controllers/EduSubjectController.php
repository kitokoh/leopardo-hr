<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduSubjectRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Matières (EDU-010, #5826). deny-by-default (EduSubjectPolicy).
 */
class EduSubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduSubject::class);

        $subjects = EduSubject::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($subjects->items())->map(fn (EduSubject $s): array => $this->payload($s)),
            'meta' => [
                'current_page' => $subjects->currentPage(),
                'last_page' => $subjects->lastPage(),
                'total' => $subjects->total(),
            ],
        ]);
    }

    public function store(StoreEduSubjectRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduSubject::class);

        $subject = EduSubject::query()->create([
            'company_id' => $actor->company_id,
            'code' => $request->input('code'),
            'name' => $request->input('name'),
        ]);

        return response()->json(['data' => $this->payload($subject->refresh())], 201);
    }

    public function update(StoreEduSubjectRequest $request, EduSubject $subject): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($subject->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', EduSubject::class);

        $subject->update($request->validated());

        return response()->json(['data' => $this->payload($subject->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduSubject $subject): array
    {
        return [
            'id' => $subject->id,
            'company_id' => $subject->company_id,
            'code' => $subject->code,
            'name' => $subject->name,
            'created_at' => $subject->created_at?->toISOString(),
            'updated_at' => $subject->updated_at?->toISOString(),
        ];
    }
}
