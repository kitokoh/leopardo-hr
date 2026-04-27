<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payroll\PayrollIndexRequest;
use App\Http\Requests\Api\V1\Payroll\StorePayrollRequest;
use App\Http\Requests\Api\V1\Payroll\UpdatePayrollRequest;
use App\Models\Payroll;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payrollService) {}

    /**
     * GET /payrolls
     * Manager: all company payrolls. Employee: own payrolls only.
     */
    public function index(PayrollIndexRequest $request): JsonResponse
    {
        $actor = $request->user();
        $query = Payroll::with('employee');

        if (!$actor->isManager()) {
            $query->where('employee_id', $actor->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->forPeriod($request->integer('month'), $request->integer('year'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->integer('per_page', 15);
        $paginated = $query->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn ($p) => $this->serialize($p)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * POST /payrolls
     * Manager creates a draft payroll.
     */
    public function store(StorePayrollRequest $request): JsonResponse
    {
        $actor   = $request->user();

        if (!$actor->isManager()) {
            abort(403);
        }

        $payroll = $this->payrollService->create($actor, $request->validated());

        return response()->json(['data' => $this->serialize($payroll->load('employee'))], 201);
    }

    /**
     * GET /payrolls/{id}
     */
    public function show(Request $request, Payroll $payroll): JsonResponse
    {
        $actor = $request->user();

        if ($payroll->company_id !== $actor->company_id) {
            abort(404);
        }

        if (!$actor->isManager() && $payroll->employee_id !== $actor->id) {
            abort(403);
        }

        return response()->json(['data' => $this->serialize($payroll->load('employee'))]);
    }

    /**
     * PUT/PATCH /payrolls/{id}
     * Manager updates a draft payroll.
     */
    public function update(UpdatePayrollRequest $request, Payroll $payroll): JsonResponse
    {
        $actor = $request->user();

        if ($payroll->company_id !== $actor->company_id) {
            abort(404);
        }

        if (!$actor->isManager()) {
            abort(403);
        }

        $payroll = $this->payrollService->update($payroll, $request->validated());

        return response()->json(['data' => $this->serialize($payroll->load('employee'))]);
    }

    /**
     * PUT /payrolls/{id}/validate
     * Manager validates a draft payroll.
     */
    public function validate(Request $request, Payroll $payroll): JsonResponse
    {
        $actor = $request->user();

        if ($payroll->company_id !== $actor->company_id) {
            abort(404);
        }

        if (!$actor->isManager()) {
            abort(403);
        }

        $payroll = $this->payrollService->validate($payroll, $actor);

        return response()->json(['data' => $this->serialize($payroll->load('employee'))]);
    }

    /**
     * DELETE /payrolls/{id}
     * Manager deletes a draft payroll.
     */
    public function destroy(Request $request, Payroll $payroll): JsonResponse
    {
        $actor = $request->user();

        if ($payroll->company_id !== $actor->company_id) {
            abort(404);
        }

        if (!$actor->isManager()) {
            abort(403);
        }

        $this->payrollService->delete($payroll);

        return response()->json(['message' => 'Payroll deleted successfully']);
    }

    private function serialize(Payroll $payroll): array
    {
        return [
            'id'                => $payroll->id,
            'employee_id'       => $payroll->employee_id,
            'employee'          => $payroll->relationLoaded('employee') ? [
                'id'         => $payroll->employee->id,
                'first_name' => $payroll->employee->first_name,
                'last_name'  => $payroll->employee->last_name,
                'email'      => $payroll->employee->email,
            ] : null,
            'period_month'      => $payroll->period_month,
            'period_year'       => $payroll->period_year,
            'gross_salary'      => $payroll->gross_salary,
            'overtime_amount'   => $payroll->overtime_amount,
            'bonuses'           => $payroll->bonuses,
            'deductions'        => $payroll->deductions,
            'cotisations'       => $payroll->cotisations,
            'ir_amount'         => $payroll->ir_amount,
            'advance_deduction' => $payroll->advance_deduction,
            'absence_deduction' => $payroll->absence_deduction,
            'penalty_deduction' => $payroll->penalty_deduction,
            'net_salary'        => $payroll->net_salary,
            'pdf_path'          => $payroll->pdf_path,
            'status'            => $payroll->status,
            'validated_by'      => $payroll->validated_by,
            'validated_at'      => $payroll->validated_at?->toIso8601String(),
            'created_at'        => $payroll->created_at?->toIso8601String(),
            'updated_at'        => $payroll->updated_at?->toIso8601String(),
        ];
    }
}
