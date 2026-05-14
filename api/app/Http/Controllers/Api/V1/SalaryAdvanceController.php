<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SalaryAdvance\DecideSalaryAdvanceRequest;
use App\Http\Requests\Api\V1\SalaryAdvance\SalaryAdvanceIndexRequest;
use App\Http\Requests\Api\V1\SalaryAdvance\StoreSalaryAdvanceRequest;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use App\Services\SalaryAdvanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryAdvanceController extends Controller
{
    public function __construct(private readonly SalaryAdvanceService $salaryAdvanceService) {}

    public function index(SalaryAdvanceIndexRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $query = SalaryAdvance::query();

        if (! $actor->isManager()) {
            $query->where('employee_id', $actor->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->integer('per_page', 15);
        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn ($a) => $this->serialize($a)),
            'meta' => ['current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(), 'per_page' => $paginated->perPage(), 'total' => $paginated->total()],
        ]);
    }

    public function store(StoreSalaryAdvanceRequest $request): JsonResponse
    {
        $advance = $this->salaryAdvanceService->create($request->user(), $request->validated());

        return response()->json(['data' => $this->serialize($advance)], 201);
    }

    public function show(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $salaryAdvance->employee_id !== $actor->id) {
            abort(403);
        }

        return response()->json(['data' => $this->serialize($salaryAdvance)]);
    }

    public function approve(DecideSalaryAdvanceRequest $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $advance = $this->salaryAdvanceService->approve($salaryAdvance, $actor, $request->validated());

        return response()->json(['data' => $this->serialize($advance)]);
    }

    public function reject(DecideSalaryAdvanceRequest $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $advance = $this->salaryAdvanceService->reject($salaryAdvance, $actor, $request->validated('decision_comment'));

        return response()->json(['data' => $this->serialize($advance)]);
    }

    public function destroy(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($salaryAdvance->employee_id !== $actor->id) {
            abort(403);
        }

        $advance = $this->salaryAdvanceService->cancel($salaryAdvance);

        return response()->json(['data' => $this->serialize($advance)]);
    }

    private function serialize(SalaryAdvance $a): array
    {
        return ['id' => $a->id, 'employee_id' => $a->employee_id, 'amount' => $a->amount, 'reason' => $a->reason, 'status' => $a->status, 'approved_by' => $a->approved_by, 'decision_comment' => $a->decision_comment, 'repayment_months' => $a->repayment_months, 'monthly_deduction' => $a->monthly_deduction, 'amount_remaining' => $a->amount_remaining, 'repayment_plan' => $a->repayment_plan, 'created_at' => $a->created_at?->toIso8601String(), 'updated_at' => $a->updated_at?->toIso8601String()];
    }
}
