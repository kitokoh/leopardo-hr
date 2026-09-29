<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Application\Actions\ArchiveEduStudentAction;
use App\Modules\EduManager\Application\Actions\CreateEduStudentAction;
use App\Modules\EduManager\Application\Actions\UpdateEduStudentAction;
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

    public function __construct(
        private readonly CreateEduStudentAction $createStudent,
        private readonly UpdateEduStudentAction $updateStudent,
        private readonly ArchiveEduStudentAction $archiveStudent,
    ) {}

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

        $student = $this->createStudent->execute($actor, $request->validated());

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

        $student = $this->updateStudent->execute($student, $request->validated());

        return response()->json(['data' => $this->payload($student)]);
    }

    public function destroy(Request $request, EduStudent $student): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($student, $actor->company_id);
        $this->authorize('delete', $student);

        $this->archiveStudent->execute($student);

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
