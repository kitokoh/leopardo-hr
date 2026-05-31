<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Absence\AbsenceIndexRequest;
use App\Http\Requests\Api\V1\Absence\RejectAbsenceRequest;
use App\Http\Requests\Api\V1\Absence\StoreAbsenceRequest;
use App\Http\Resources\Api\V1\AbsenceResource;
use App\Models\Absence;
use App\Models\Employee;
use App\Services\AbsenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class AbsenceController extends Controller
{
    public function __construct(private readonly AbsenceService $absenceService) {}

    public function index(AbsenceIndexRequest $request): AnonymousResourceCollection
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
                'employee:id,first_name,last_name,email,company_id',
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

        return AbsenceResource::collection($paginated);
    }

    public function store(StoreAbsenceRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $absence = $this->absenceService->create($actor, $request->validated());

        return (new AbsenceResource($absence->load([
            'absenceType:id,name,code,deducts_leave',
            'employee:id,first_name,last_name,email,company_id',
        ])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Absence $absence): AbsenceResource
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($absence->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! $actor->isManager() && $absence->employee_id !== $actor->id) {
            abort(403);
        }

        return new AbsenceResource($absence->load([
            'absenceType:id,name,code,deducts_leave',
            'employee:id,first_name,last_name,email,company_id',
        ]));
    }

    public function approve(Request $request, Absence $absence): AbsenceResource
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

        return new AbsenceResource($absence->load([
            'absenceType:id,name,code,deducts_leave',
            'employee:id,first_name,last_name,email,company_id',
        ]));
    }

    public function reject(RejectAbsenceRequest $request, Absence $absence): AbsenceResource
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

        return new AbsenceResource($absence->load([
            'absenceType:id,name,code,deducts_leave',
            'employee:id,first_name,last_name,email,company_id',
        ]));
    }

    public function destroy(Request $request, Absence $absence): AbsenceResource
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

        return new AbsenceResource($absence->load([
            'absenceType:id,name,code,deducts_leave',
            'employee:id,first_name,last_name,email,company_id',
        ]));
    }
}
