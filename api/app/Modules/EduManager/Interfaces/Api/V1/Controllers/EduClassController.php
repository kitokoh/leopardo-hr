<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduTeacherSubject;
use App\Modules\EduManager\Infrastructure\Services\EduAcademicYearService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\AssignEduTeacherRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduClassRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\UpdateEduClassRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des classes et affectations enseignants — EDU-010 (issue #5826,
 * EDU-003/EDU-009).
 */
class EduClassController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduAcademicYearService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduClass::class);

        $query = EduClass::query()->with(['campus:id,code,name', 'academicYear:id,name']);

        // Périmètre enseignant : ses classes uniquement.
        if (! \App\Modules\EduManager\Domain\Access\EduAccess::isAdmin($actor)) {
            $ids = \App\Modules\EduManager\Domain\Access\EduAccess::teacherClassIds($actor);
            $query->where(function ($builder) use ($actor, $ids): void {
                $builder->where('teacher_id', $actor->id)->orWhereIn('id', $ids);
            });
        } else {
            $query->where('company_id', $actor->company_id);
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', (int) $request->input('academic_year_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $classes = $query->orderBy('name')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($classes->items())->map(fn (EduClass $class): array => $this->payload($class)),
            'meta' => [
                'current_page' => $classes->currentPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
            ],
        ]);
    }

    public function store(StoreEduClassRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduClass::class);

        $class = $this->service->createClass($actor, $request->validated());

        return response()->json(['data' => $this->payload($class)], 201);
    }

    public function show(Request $request, EduClass $class): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($class, $actor->company_id);
        $this->authorize('view', $class);

        return response()->json(['data' => $this->payload($class->load('campus:id,code,name'))]);
    }

    public function update(UpdateEduClassRequest $request, EduClass $class): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($class, $actor->company_id);
        $this->authorize('update', $class);

        $class->update($request->validated());

        return response()->json(['data' => $this->payload($class->refresh())]);
    }

    public function destroy(Request $request, EduClass $class): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($class, $actor->company_id);
        $this->authorize('delete', $class);

        $class->delete();

        return response()->json(null, 204);
    }

    public function assignTeacher(AssignEduTeacherRequest $request, EduClass $class): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($class, $actor->company_id);
        $this->authorize('update', $class);

        $assignment = $this->service->assignTeacher($actor, array_merge($request->validated(), [
            'class_id' => (int) $class->getAttribute('id'),
        ]));

        return response()->json(['data' => $this->assignmentPayload($assignment)], 201);
    }

    public function removeTeacher(Request $request, EduTeacherSubject $assignment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($assignment, $actor->company_id);
        $this->authorize('delete', $assignment);

        // Historique conservé : passage en inactif, pas de suppression.
        $assignment->update(['status' => EduTeacherSubject::STATUS_INACTIVE]);

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduClass $class): array
    {
        return [
            'id' => (int) $class->getAttribute('id'),
            'campus_id' => $class->campus_id,
            'campus' => $class->relationLoaded('campus')
                ? ['id' => (int) ($class->campus?->getAttribute('id') ?? 0), 'code' => $class->campus?->code, 'name' => $class->campus?->name]
                : null,
            'academic_year_id' => $class->academic_year_id,
            'code' => $class->code,
            'name' => $class->name,
            'level' => $class->level,
            'teacher_id' => $class->teacher_id,
            'capacity' => $class->capacity,
            'status' => $class->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentPayload(EduTeacherSubject $assignment): array
    {
        return [
            'id' => (int) $assignment->getAttribute('id'),
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'status' => $assignment->status,
        ];
    }
}
