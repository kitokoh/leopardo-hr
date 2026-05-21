<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Absence\AbsenceIndexRequest;
use App\Http\Requests\Api\V1\Absence\RejectAbsenceRequest;
use App\Http\Requests\Api\V1\Absence\StoreAbsenceRequest;
use App\Models\Absence;
use App\Models\Employee;
use App\Services\AbsenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AbsenceController extends Controller
{
    public function __construct(private readonly AbsenceService $absenceService) {}

    public function index(AbsenceIndexRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $query = Absence::query()
            ->select([
                'id',
                'company_id',
                'employee_id',
                'absence_type_id',
                'start_date',
                'end_date',
                'days_count',
                'status',
                'reason',
                'approved_by',
                'rejected_reason',
                'created_at',
                'updated_at',
            ])
            ->with([
                'absenceType:id,name,code,deducts_leave',
                'employee:id,first_name,last_name',
            ]);

        // RBAC: employee sees only own absences
        if (! $actor->isManager()) {
            $query->where('employee_id', $actor->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('month') && $request->filled('year')) {
            $month = $request->integer('month');
            $year = $request->integer('year');
            $periodStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
            $periodEnd = $periodStart->copy()->endOfMonth();

            $query
                ->where('start_date', '<=', $periodEnd->toDateString())
                ->where('end_date', '>=', $periodStart->toDateString());
        }

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDir = (string) ($validated['sort_dir'] ?? 'desc');

        $paginated = $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn ($a) => $this->serialize($a)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function store(StoreAbsenceRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $absence = $this->absenceService->create($actor, $request->validated());

        return response()->json(['data' => $this->serialize($absence->load('absenceType'))], 201);
    }

    public function show(Request $request, Absence $absence): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($absence->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! $actor->isManager() && $absence->employee_id !== $actor->id) {
            abort(403);
        }

        return response()->json(['data' => $this->serialize($absence->load('absenceType'))]);
    }

    public function approve(Request $request, Absence $absence): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($absence->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! $actor->isManager()) {
            abort(403);
        }

        $absence = $this->absenceService->approve($absence, $actor);

        return response()->json(['data' => $this->serialize($absence->load('absenceType'))]);
    }

    public function reject(RejectAbsenceRequest $request, Absence $absence): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($absence->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! $actor->isManager()) {
            abort(403);
        }

        $absence = $this->absenceService->reject($absence, $request->validated('rejected_reason'));

        return response()->json(['data' => $this->serialize($absence->load('absenceType'))]);
    }

    public function destroy(Request $request, Absence $absence): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($absence->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($absence->employee_id !== $actor->id) {
            abort(403);
        }

        $absence = $this->absenceService->cancel($absence);

        return response()->json(['data' => $this->serialize($absence->load('absenceType'))]);
    }

    private function serialize(Absence $absence): array
    {
        return [
            'id' => $absence->id,
            'employee_id' => $absence->employee_id,
            'absence_type_id' => $absence->absence_type_id,
            'absence_type' => $absence->relationLoaded('absenceType') ? [
                'id' => $absence->absenceType->id,
                'name' => $absence->absenceType->name,
                'code' => $absence->absenceType->code,
                'deducts_leave' => $absence->absenceType->deducts_leave,
            ] : null,
            'absenceType' => $absence->relationLoaded('absenceType') ? [
                'id' => $absence->absenceType->id,
                'name' => $absence->absenceType->name,
                'code' => $absence->absenceType->code,
                'deducts_leave' => $absence->absenceType->deducts_leave,
            ] : null,
            'employee_name' => $absence->relationLoaded('employee') && $absence->employee !== null
                ? trim(($absence->employee->first_name ?? '').' '.($absence->employee->last_name ?? ''))
                : null,
            'type' => $absence->relationLoaded('absenceType') && $absence->absenceType !== null
                ? $absence->absenceType->name
                : null,
            'start_date' => $absence->start_date?->toDateString(),
            'end_date' => $absence->end_date?->toDateString(),
            'days_count' => $absence->days_count,
            'status' => $absence->status,
            'reason' => $absence->reason,
            'approved_by' => $absence->approved_by,
            'rejected_reason' => $absence->rejected_reason,
            'created_at' => $absence->created_at?->toIso8601String(),
            'updated_at' => $absence->updated_at?->toIso8601String(),
        ];
    }
}
