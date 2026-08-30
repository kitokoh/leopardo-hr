<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduTeacher;
use App\Modules\EduManager\Domain\Models\EduTeacherAssignment;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduTeacherRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Enseignants & affectations (EDU-010, #5826). deny-by-default
 * (EduTeacherPolicy) : CRUD direction, lecture tenant.
 */
class EduTeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduTeacher::class);

        $teachers = EduTeacher::query()
            ->where('company_id', $actor->company_id)
            ->with('employee:id,first_name,last_name')
            ->orderBy('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($teachers->items())->map(fn (EduTeacher $t): array => $this->payload($t)),
            'meta' => [
                'current_page' => $teachers->currentPage(),
                'last_page' => $teachers->lastPage(),
                'total' => $teachers->total(),
            ],
        ]);
    }

    public function store(StoreEduTeacherRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduTeacher::class);

        $teacher = EduTeacher::query()->create([
            'company_id' => $actor->company_id,
            'employee_id' => $request->input('employee_id'),
        ]);

        return response()->json(['data' => $this->payload($teacher->refresh())], 201);
    }

    public function assign(Request $request, EduTeacher $teacher): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($teacher->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $teacher);

        $request->validate([
            'class_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
        ]);

        $assignment = EduTeacherAssignment::query()->firstOrCreate([
            'company_id' => (string) $actor->company_id,
            'class_id' => $request->integer('class_id'),
            'subject_id' => $request->integer('subject_id'),
            'academic_year_id' => $request->integer('academic_year_id'),
        ], [
            'teacher_id' => $teacher->id,
            'status' => EduTeacherAssignment::STATUS_ACTIVE,
        ]);

        if ($assignment->teacher_id !== $teacher->id) {
            $assignment->forceFill(['teacher_id' => $teacher->id, 'status' => EduTeacherAssignment::STATUS_ACTIVE])->save();
        }

        return response()->json(['data' => [
            'id' => $assignment->id,
            'teacher_id' => $assignment->teacher_id,
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'academic_year_id' => $assignment->academic_year_id,
            'status' => $assignment->status,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduTeacher $teacher): array
    {
        return [
            'id' => $teacher->id,
            'company_id' => $teacher->company_id,
            'employee_id' => $teacher->employee_id,
            'employee' => $teacher->relationLoaded('employee')
                ? [
                    'id' => $teacher->employee?->id,
                    'first_name' => $teacher->employee?->first_name,
                    'last_name' => $teacher->employee?->last_name,
                ]
                : null,
            'created_at' => $teacher->created_at?->toISOString(),
        ];
    }
}
