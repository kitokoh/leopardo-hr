<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Infrastructure\Services\EduAttendanceService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\CorrectEduAttendanceRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduAttendanceRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de la présence scolaire — EDU-010 (issue #5826, EDU-005).
 *
 * Saisie par classe (idempotente), corrections versionnées ; périmètre
 * enseignant = ses classes (Policy).
 */
class EduAttendanceController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduAttendanceService $attendances) {}

    public function index(Request $request, EduClass $class): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($class, $actor->company_id);
        $this->authorize('view', $class);

        $query = EduAttendance::query()->with('student:id,student_number,display_name')
            ->where('company_id', $actor->company_id)
            ->where('class_id', (int) $class->getAttribute('id'));

        if ($request->filled('date')) {
            $query->where('attendance_date', $request->input('date'));
        }

        $attendances = $query->orderBy('attendance_date')->orderBy('id')->get();

        return response()->json([
            'data' => $attendances->map(fn (EduAttendance $attendance): array => $this->payload($attendance)),
        ]);
    }

    public function store(StoreEduAttendanceRequest $request, EduClass $class): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($class, $actor->company_id);
        $this->authorize('createForClass', [EduAttendance::class, $class]);

        $attendance = $this->attendances->record($actor, $class, $request->validated());

        return response()->json(['data' => $this->payload($attendance->load('student:id,student_number,display_name'))], 201);
    }

    public function correct(CorrectEduAttendanceRequest $request, EduAttendance $attendance): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($attendance, $actor->company_id);
        $this->authorize('correct', $attendance);

        $updated = $this->attendances->correct($actor, $attendance, $request->validated());

        return response()->json(['data' => $this->payload($updated->load('student:id,student_number,display_name'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduAttendance $attendance): array
    {
        return [
            'id' => (int) $attendance->getAttribute('id'),
            'class_id' => $attendance->class_id,
            'student_id' => $attendance->student_id,
            'student' => $attendance->relationLoaded('student') && $attendance->student !== null
                ? [
                    'id' => (int) $attendance->student->getAttribute('id'),
                    'student_number' => $attendance->student->student_number,
                    'display_name' => $attendance->student->display_name,
                ]
                : null,
            'attendance_date' => $attendance->attendance_date->toDateString(),
            'status' => $attendance->status,
            'reason' => $attendance->reason,
            'justification' => $attendance->justification,
        ];
    }
}
