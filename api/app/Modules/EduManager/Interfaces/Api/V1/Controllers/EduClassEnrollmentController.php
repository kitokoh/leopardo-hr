<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduClassEnrollment;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduClassEnrollmentRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des inscriptions aux classes — EDU-011 (issue #5827).
 *
 * Effectifs d'une classe (direction : gestion ; enseignant : lecture de SES
 * classes uniquement — EduClassPolicy), inscription idempotente
 * (UNIQUE company_id, class_id, student_id) et désinscription (direction,
 * soft-status inactive, historique conservé).
 */
class EduClassEnrollmentController extends Controller
{
    use ChecksEduSolution;

    public function index(Request $request, EduClass $class): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($class, $actor->company_id);
        $this->authorize('view', $class);

        $enrollments = EduClassEnrollment::query()
            ->with('student:id,student_number,display_name,status')
            ->where('company_id', $actor->company_id)
            ->where('class_id', $class->getAttribute('id'))
            ->where('status', EduClassEnrollment::STATUS_ACTIVE)
            ->orderByDesc('enrolled_at')
            ->paginate((int) ($request->input('per_page') ?? 50));

        return response()->json([
            'data' => [
                'class' => [
                    'id' => (int) $class->getAttribute('id'),
                    'code' => $class->code,
                    'name' => $class->name,
                ],
                'students' => collect($enrollments->items())->map(
                    fn (EduClassEnrollment $enrollment): array => [
                        'enrollment_id' => (int) $enrollment->getAttribute('id'),
                        'enrolled_at' => $enrollment->enrolled_at->toIso8601String(),
                        'student' => $enrollment->student !== null
                            ? [
                                'id' => (int) $enrollment->student->getAttribute('id'),
                                'student_number' => $enrollment->student->student_number,
                                'display_name' => $enrollment->student->display_name,
                            ]
                            : null,
                    ]
                ),
            ],
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    public function store(StoreEduClassEnrollmentRequest $request, EduClass $class): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($class, $actor->company_id);
        abort_unless(EduAccess::isAdmin($actor), 403, 'EDU_ADMIN_ONLY');

        $data = $request->validated();
        $payload = [
            'company_id' => $actor->company_id,
            'class_id' => (int) $class->getAttribute('id'),
            'student_id' => (int) $data['student_id'],
            'academic_year_id' => (int) $data['academic_year_id'],
            'enrolled_at' => $data['enrolled_at'] ?? now(),
            'status' => EduClassEnrollment::STATUS_ACTIVE,
            'enrolled_by' => $actor->id,
        ];

        try {
            /** @var EduClassEnrollment $enrollment */
            $enrollment = EduClassEnrollment::query()->create($payload);
        } catch (UniqueConstraintViolationException) {
            /** @var EduClassEnrollment $enrollment */
            $enrollment = EduClassEnrollment::query()
                ->where('company_id', $actor->company_id)
                ->where('class_id', $class->getAttribute('id'))
                ->where('student_id', $data['student_id'])
                ->firstOrFail();
        }

        return response()->json([
            'data' => [
                'enrollment_id' => (int) $enrollment->getAttribute('id'),
                'class_id' => (int) $enrollment->getAttribute('class_id'),
                'student_id' => (int) $enrollment->getAttribute('student_id'),
                'status' => $enrollment->status,
                'enrolled_at' => $enrollment->enrolled_at->toIso8601String(),
            ],
        ], 201);
    }

    public function destroy(Request $request, EduClassEnrollment $enrollment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($enrollment, $actor->company_id);
        $this->authorize('delete', $enrollment);

        $enrollment->update(['status' => EduClassEnrollment::STATUS_INACTIVE]);

        return response()->json(['data' => ['enrollment_id' => (int) $enrollment->getAttribute('id'), 'status' => $enrollment->status]]);
    }
}
