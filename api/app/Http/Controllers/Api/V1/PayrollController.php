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

    public function index(PayrollIndexRequest $request): JsonResponse
    {
        $actor = $request->user();
        $query = Payroll::query()
            ->select([
                'id',
                'company_id',
                'employee_id',
                'period_month',
                'period_year',
                'gross_salary',
                'overtime_amount',
                'bonuses',
                'deductions',
                'cotisations',
                'ir_amount',
                'advance_deduction',
                'absence_deduction',
                'penalty_deduction',
                'net_salary',
                'pdf_path',
                'status',
                'validated_by',
                'validated_at',
                'created_at',
                'updated_at',
            ])
            ->with(['employee:id,company_id,first_name,last_name,email']);

        if (! $actor->isManager()) {
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
        $paginated = $query->orderByDesc('period_year')->orderByDesc('period_month')->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn ($p) => $this->serialize($p)),
            'meta' => ['current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(), 'per_page' => $paginated->perPage(), 'total' => $paginated->total()],
        ]);
    }

    public function store(StorePayrollRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $payroll = $this->payrollService->create($actor, $request->validated());

        return response()->json(['data' => $this->serialize($payroll->load('employee'))], 201);
    }

    public function show(Request $request, Payroll $payroll): JsonResponse
    {
        $actor = $request->user();
        if ($payroll->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $payroll->employee_id !== $actor->id) {
            abort(403);
        }

        return response()->json(['data' => $this->serialize($payroll->load('employee'))]);
    }

    public function update(UpdatePayrollRequest $request, Payroll $payroll): JsonResponse
    {
        $actor = $request->user();
        if ($payroll->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $payroll = $this->payrollService->update($payroll, $request->validated());

        return response()->json(['data' => $this->serialize($payroll->load('employee'))]);
    }

    public function validatePayroll(Request $request, Payroll $payroll): JsonResponse
    {
        $actor = $request->user();
        if ($payroll->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $payroll = $this->payrollService->validate($payroll, $actor);

        return response()->json(['data' => $this->serialize($payroll->load('employee'))]);
    }

    public function destroy(Request $request, Payroll $payroll): JsonResponse
    {
        $actor = $request->user();
        if ($payroll->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $this->payrollService->delete($payroll);

        return response()->json(['message' => 'Payroll deleted successfully']);
    }

    private function serialize(Payroll $p): array
    {
        return [
            'id' => $p->id, 'employee_id' => $p->employee_id,
            'employee' => $p->relationLoaded('employee') ? ['id' => $p->employee->id, 'first_name' => $p->employee->first_name, 'last_name' => $p->employee->last_name, 'email' => $p->employee->email] : null,
            'period_month' => $p->period_month, 'period_year' => $p->period_year,
            'gross_salary' => $p->gross_salary, 'overtime_amount' => $p->overtime_amount,
            'bonuses' => $p->bonuses, 'deductions' => $p->deductions, 'cotisations' => $p->cotisations,
            'ir_amount' => $p->ir_amount, 'advance_deduction' => $p->advance_deduction,
            'absence_deduction' => $p->absence_deduction, 'penalty_deduction' => $p->penalty_deduction,
            'net_salary' => $p->net_salary, 'pdf_path' => $p->pdf_path, 'status' => $p->status,
            'validated_by' => $p->validated_by, 'validated_at' => $p->validated_at?->toIso8601String(),
            'created_at' => $p->created_at?->toIso8601String(), 'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }
}
