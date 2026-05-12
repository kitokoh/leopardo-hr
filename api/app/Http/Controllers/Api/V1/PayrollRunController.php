<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollRunController extends Controller
{
    public function __construct(private readonly PayrollCalculator $calculator) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $query = PayrollRun::query()
            ->where('company_id', $actor->company_id)
            ->withCount('paySlips');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $runs = $query->orderByDesc('period_start')->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $runs->items(),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'country_code' => 'required|string|size:2|in:DZ,MA,TN,FR,TR,SN',
            'notes' => 'nullable|string|max:2000',
        ]);

        $run = PayrollRun::create([
            'company_id' => $actor->company_id,
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'country_code' => $validated['country_code'],
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(['data' => $run], 201);
    }

    public function show(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $payrollRun->loadCount('paySlips');

        return response()->json(['data' => $payrollRun]);
    }

    public function calculate(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        if (! in_array($payrollRun->status, ['draft', 'calculated'])) {
            return response()->json(['message' => 'Payroll run cannot be recalculated in current status.'], 422);
        }

        $payrollRun->update(['status' => 'calculating']);
        $run = $this->calculator->calculateRun($payrollRun);

        return response()->json(['data' => $run->loadCount('paySlips')]);
    }

    public function validateRun(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        if ($payrollRun->status !== 'calculated') {
            return response()->json(['message' => 'Only calculated payroll runs can be validated.'], 422);
        }

        DB::transaction(function () use ($payrollRun, $actor) {
            $payrollRun->update([
                'status' => 'validated',
                'validated_by' => $actor->id,
                'validated_at' => now(),
            ]);

            $payrollRun->paySlips()->update(['status' => 'validated']);
        });

        return response()->json(['data' => $payrollRun->refresh()->loadCount('paySlips')]);
    }

    public function cancel(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        if (in_array($payrollRun->status, ['paid', 'cancelled'])) {
            return response()->json(['message' => 'Payroll run cannot be cancelled.'], 422);
        }

        $payrollRun->update(['status' => 'cancelled']);

        return response()->json(['data' => $payrollRun->refresh()]);
    }

    public function summary(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $slips = $payrollRun->paySlips()->with('employee:id,first_name,last_name')->get();

        return response()->json([
            'data' => [
                'run' => $payrollRun,
                'total_gross' => $payrollRun->total_gross,
                'total_deductions' => $payrollRun->total_deductions,
                'total_net' => $payrollRun->total_net,
                'total_employer_cost' => $payrollRun->total_employer_cost,
                'employee_count' => $payrollRun->employee_count,
                'slips' => $slips->map(fn ($s) => [
                    'id' => $s->id,
                    'employee_id' => $s->employee_id,
                    'employee' => $s->relationLoaded('employee') ? [
                        'id' => $s->employee->id,
                        'first_name' => $s->employee->first_name,
                        'last_name' => $s->employee->last_name,
                    ] : null,
                    'gross_salary' => $s->gross_salary,
                    'net_salary' => $s->net_salary,
                    'total_cost' => $s->total_cost,
                ]),
            ],
        ]);
    }
}
