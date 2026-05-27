<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payroll\PayrollIndexRequest;
use App\Http\Requests\Api\V1\Payroll\StorePayrollRequest;
use App\Http\Requests\Api\V1\Payroll\UpdatePayrollRequest;
use App\Http\Resources\Api\V1\PayrollResource;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payrollService) {}

    public function index(PayrollIndexRequest $request): JsonResponse
    {
        /** @var Employee $actor */
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

        return PayrollResource::collection($paginated)->response();
    }

    public function store(StorePayrollRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $payroll = $this->payrollService->create($actor, $request->validated());

        return (new PayrollResource($payroll->load('employee')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Payroll $payroll): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payroll->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $payroll->employee_id !== $actor->id) {
            abort(403);
        }

        return (new PayrollResource($payroll->load('employee')))->response();
    }

    public function update(UpdatePayrollRequest $request, Payroll $payroll): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payroll->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $payroll = $this->payrollService->update($payroll, $request->validated());

        return (new PayrollResource($payroll->load('employee')))->response();
    }

    public function validatePayroll(Request $request, Payroll $payroll): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payroll->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $payroll = $this->payrollService->validate($payroll, $actor);

        return (new PayrollResource($payroll->load('employee')))->response();
    }

    public function destroy(Request $request, Payroll $payroll): JsonResponse
    {
        /** @var Employee $actor */
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
}
