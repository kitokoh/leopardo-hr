<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduStudentRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\UpdateEduStudentRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des élèves — EDU-010 (issue #5826, EDU-002).
 *
 * PII : `display_name` exposé en clair, `birth_date` et métadonnées
 * chiffrées au repos — jamais loggées, jamais hors tenant.
 */
class EduStudentController extends Controller
{
    use ChecksEduSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduStudent::class);

        $query = EduStudent::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $search = (string) $request->input('q');
            $query->where(function ($builder) use ($search): void {
                $builder->where('display_name', 'ilike', "%{$search}%")
                    ->orWhere('student_number', 'ilike', "%{$search}%");
            });
        }

        $students = $query->orderBy('display_name')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($students->items())->map(fn (EduStudent $student): array => $this->payload($student)),
            'meta' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    public function store(StoreEduStudentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduStudent::class);

        $payload = $request->validated();
        if (isset($payload['birth_date'])) {
            $payload['birth_date_encrypted'] = $payload['birth_date'];
            unset($payload['birth_date']);
        }

        /** @var EduStudent $student */
        $student = EduStudent::query()->create(array_merge($payload, [
            'company_id' => $actor->company_id,
        ]));

        return response()->json(['data' => $this->payload($student)], 201);
    }

    public function show(Request $request, EduStudent $student): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($student, $actor->company_id);
        $this->authorize('view', $student);

        return response()->json(['data' => $this->payload($student)]);
    }

    public function update(UpdateEduStudentRequest $request, EduStudent $student): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($student, $actor->company_id);
        $this->authorize('update', $student);

        $payload = $request->validated();
        if (isset($payload['birth_date'])) {
            $payload['birth_date_encrypted'] = $payload['birth_date'];
            unset($payload['birth_date']);
        }

        $student->update($payload);

        return response()->json(['data' => $this->payload($student->refresh())]);
    }

    public function destroy(Request $request, EduStudent $student): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($student, $actor->company_id);
        $this->authorize('delete', $student);

        // Archivage (RGPD) — suppression physique interdite pour les élèves.
        $student->update(['status' => EduStudent::STATUS_ARCHIVED]);

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduStudent $student): array
    {
        return [
            'id' => (int) $student->getAttribute('id'),
            'student_number' => $student->student_number,
            'display_name' => $student->display_name,
            'status' => $student->status,
        ];
    }
}
