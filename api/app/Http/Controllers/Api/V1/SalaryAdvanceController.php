<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SalaryAdvance\DecideSalaryAdvanceRequest;
use App\Http\Requests\Api\V1\SalaryAdvance\SalaryAdvanceIndexRequest;
use App\Http\Requests\Api\V1\SalaryAdvance\StoreSalaryAdvanceRequest;
use App\Models\SalaryAdvance;
use App\Services\SalaryAdvanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryAdvanceController extends Controller
{
    public function __construct(private readonly SalaryAdvanceService $salaryAdvanceService) {}

    /**
     * GET /salary-advances
     * Employee: own advances only. Manager: all company advances.
     */
    public function index(SalaryAdvanceIndexRequest $request): JsonResponse
    {
        $actor = $request->user();
        $query = SalaryAdvance::query();

        if (!$actor->isManager()) {
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
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * POST /salary-advances
     * Employee creates a new advance request.
     */
    public function store(StoreSalaryAdvanceRequest $request): JsonResponse
    {
        $actor   = $request->user();
        $advance = $this->salaryAdvanceService->create($actor, $request->validated());

        return response()->json(['data' => $this->serialize($advance)], 201);
    }

    /**
     * GET /salary-advances/{id}
     */
    public function show(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        $actor = $request->user();

        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }

        if (!$actor->isManager() && $salaryAdvance->employee_id !== $actor->id) {
            abort(403);
        }

        return response()->json(['data' => $this->serialize($salaryAdvance)]);
    }

    /**
     * PUT /salary-advances/{id}/approve
     * Manager approves a pending advance.
     */
    public function approve(DecideSalaryAdvanceRequest $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        $actor = $request->user();

        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }

        if (!$actor->isManager()) {
            abort(403);
        }

        $advance = $this->salaryAdvanceService->approve($salaryAdvance, $actor, $request->validated());

        return response()->json(['data' => $this->serialize($advance)]);
    }

    /**
     * PUT /salary-advances/{id}/reject
     * Manager rejects a pending advance.
     */
    public function reject(DecideSalaryAdvanceRequest $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        $actor = $request->user();

        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }

        if (!$actor->isManager()) {
            abort(403);
        }

        $advance = $this->salaryAdvanceService->reject(
            $salaryAdvance,
            $actor,
            $request->validated('decision_comment')
        );

        return response()->json(['data' => $this->serialize($advance)]);
    }

    /**
     * DELETE /salary-advances/{id}
     * Employee cancels their own pending advance.
     */
    public function destroy(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
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

    private function serialize(SalaryAdvance $advance): array
    {
        return [
            'id'                => $advance->id,
            'employee_id'       => $advance->employee_id,
            'amount'            => $advance->amount,
            'reason'            => $advance->reason,
            'status'            => $advance->status,
            'approved_by'       => $advance->approved_by,
            'decision_comment'  => $advance->decision_comment,
            'repayment_months'  => $advance->repayment_months,
            'monthly_deduction' => $advance->monthly_deduction,
            'amount_remaining'  => $advance->amount_remaining,
            'repayment_plan'    => $advance->repayment_plan,
            'created_at'        => $advance->created_at?->toIso8601String(),
            'updated_at'        => $advance->updated_at?->toIso8601String(),
        ];
    }
}
