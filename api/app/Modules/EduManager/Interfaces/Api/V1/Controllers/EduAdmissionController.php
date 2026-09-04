<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Infrastructure\Services\EduAdmissionService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\ConvertEduAdmissionRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduAdmissionRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des admissions — EDU-010 (issue #5826, EDU-004).
 *
 * Création idempotente (external_id), conversion en élève idempotente avec
 * consentement obligatoire. Direction uniquement (PII enfants).
 */
class EduAdmissionController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduAdmissionService $admissions) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduAdmission::class);

        $query = EduAdmission::query()->with('student:id,student_number,display_name')
            ->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', (int) $request->input('academic_year_id'));
        }

        $admissions = $query->orderByDesc('applied_at')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($admissions->items())->map(fn (EduAdmission $admission): array => $this->payload($admission)),
            'meta' => [
                'current_page' => $admissions->currentPage(),
                'per_page' => $admissions->perPage(),
                'total' => $admissions->total(),
            ],
        ]);
    }

    public function store(StoreEduAdmissionRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduAdmission::class);

        $admission = $this->admissions->create($actor, $request->validated());

        return response()->json(['data' => $this->payload($admission)], 201);
    }

    public function show(Request $request, EduAdmission $admission): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($admission, $actor->company_id);
        $this->authorize('view', $admission);

        return response()->json(['data' => $this->payload($admission->load('student:id,student_number,display_name'))]);
    }

    public function convert(ConvertEduAdmissionRequest $request, EduAdmission $admission): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($admission, $actor->company_id);
        $this->authorize('convert', $admission);

        $student = $this->admissions->convertToStudent($actor, $admission, $request->validated());

        return response()->json([
            'data' => [
                'admission' => $this->payload($admission->refresh()),
                'student_id' => (int) $student->getAttribute('id'),
                'student_number' => $student->student_number,
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduAdmission $admission): array
    {
        return [
            'id' => (int) $admission->getAttribute('id'),
            'admission_number' => $admission->admission_number,
            'academic_year_id' => $admission->academic_year_id,
            'campus_id' => $admission->campus_id,
            'applicant_first_name' => $admission->applicant_first_name,
            'applicant_last_name' => $admission->applicant_last_name,
            'applicant_email' => $admission->applicant_email,
            'applicant_birth_date' => $admission->applicant_birth_date?->toDateString(),
            'status' => $admission->status,
            'source' => $admission->source,
            'consent_contact' => $admission->consent_contact,
            'applied_at' => $admission->applied_at->toDateString(),
            'converted_at' => $admission->converted_at?->toIso8601String(),
            'student' => $admission->relationLoaded('student') && $admission->student !== null
                ? [
                    'id' => (int) $admission->student->getAttribute('id'),
                    'student_number' => $admission->student->student_number,
                    'display_name' => $admission->student->display_name,
                ]
                : null,
        ];
    }
}
