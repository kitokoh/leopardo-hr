<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduAttendanceRecord;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Policies\EduAttendancePolicy;
use App\Modules\EduManager\Infrastructure\Services\EduAttendanceService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\RecordEduAttendanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Présence scolaire (EDU-010, #5826). deny-by-default (EduAttendancePolicy) :
 * direction = tout ; enseignant = ses classes uniquement.
 */
class EduAttendanceController extends Controller
{
    public function __construct(private readonly EduAttendanceService $attendance) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduAttendanceRecord::class);

        $query = EduAttendanceRecord::query()->where('company_id', $actor->company_id);

        if (! $actor->isManager()) {
            $query->whereIn('class_id', $this->teacherClassIds($actor));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->integer('class_id'));
        }

        if ($request->filled('session_date')) {
            $query->where('session_date', $request->string('session_date')->toString());
        }

        $records = $query->orderByDesc('session_date')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($records->items())->map(fn (EduAttendanceRecord $r): array => $this->payload($r)),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    public function record(RecordEduAttendanceRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $class = EduClass::query()
            ->where('company_id', $actor->company_id)
            ->find($request->integer('class_id'));

        abort_if(! $class instanceof EduClass, 404);

        // Policy explicite EduAttendancePolicy (pas la policy du modèle EduClass).
        $this->authorize('create', [EduAttendanceRecord::class, $class]);

        $record = $this->attendance->record($actor, $request->validated());

        return response()->json(['data' => $this->payload($record)], 201);
    }

    public function correct(Request $request, EduAttendanceRecord $record): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($record->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('correct', $record);

        $request->validate([
            'status' => ['required', 'in:present,absent,late,excused'],
            'reason' => ['nullable', 'string', 'max:255'],
            'justified' => ['nullable', 'boolean'],
            'correction_reason' => ['required', 'string', 'max:255'],
        ]);

        $record = $this->attendance->correct(
            $actor,
            $record,
            $request->string('status')->toString(),
            $request->filled('reason') ? $request->string('reason')->toString() : null,
            $request->boolean('justified'),
            $request->string('correction_reason')->toString(),
        );

        return response()->json(['data' => $this->payload($record)]);
    }

    /**
     * @return array<int, int>
     */
    private function teacherClassIds(Employee $actor): array
    {
        $policy = new EduAttendancePolicy;

        return $policy->taughtClassIds($actor);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduAttendanceRecord $record): array
    {
        return [
            'id' => $record->id,
            'company_id' => $record->company_id,
            'class_id' => $record->class_id,
            'student_id' => $record->student_id,
            'subject_id' => $record->subject_id,
            'session_date' => $record->session_date->toDateString(),
            'session_label' => $record->session_label,
            'status' => $record->status,
            'reason' => $record->reason,
            'justified' => $record->justified,
            'recorded_by' => $record->recorded_by,
            'version' => $record->version,
            'previous_status' => $record->previous_status,
            'correction_reason' => $record->correction_reason,
            'corrected_by' => $record->corrected_by,
            'corrected_at' => $record->corrected_at?->toISOString(),
            'created_at' => $record->created_at?->toISOString(),
            'updated_at' => $record->updated_at?->toISOString(),
        ];
    }
}
