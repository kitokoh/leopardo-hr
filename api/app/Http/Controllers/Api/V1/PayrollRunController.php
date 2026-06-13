<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PayrollRunResource;
use App\Jobs\WarmPaySlipPdfPathsForPayrollRunJob;
use App\Models\Employee;
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
        if ($actor->isManager() === false) {
            abort(403);
        }

        $query = PayrollRun::query()
            ->where('company_id', $actor->company_id)
            ->withCount('paySlips');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $runs = $query->orderByDesc('period_start')->paginate($request->integer('per_page', 15));

        return PayrollRunResource::collection($runs)->response();
    }

    public function store(\App\Http\Requests\Api\V1\Payroll\StorePayrollRunRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($actor->isManager() === false) {
            abort(403);
        }

        $validated = $request->validated();

        $run = PayrollRun::create([
            'company_id' => $actor->company_id,
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'country_code' => $validated['country_code'],
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
        ]);

        return (new PayrollRunResource($run))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        $payrollRun->loadCount('paySlips');

        return (new PayrollRunResource($payrollRun))->response();
    }

    public function calculate(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        if (in_array($payrollRun->status, ['draft', 'calculated'], true) === false) {
            return response()->json(['message' => 'Payroll run cannot be recalculated in current status.'], 422);
        }

        $payrollRun->update(['status' => 'calculating']);
        $run = $this->calculator->calculateRun($payrollRun);

        return (new PayrollRunResource($run->loadCount('paySlips')))->response();
    }

    public function validateRun(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
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

        if (config('performance.payroll.queue_pdf_warmup', true)) {
            WarmPaySlipPdfPathsForPayrollRunJob::dispatch($payrollRun->id);
        }

        return (new PayrollRunResource($payrollRun->refresh()->loadCount('paySlips')))->response();
    }

    public function cancel(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        if (in_array($payrollRun->status, ['paid', 'cancelled'], true)) {
            return response()->json(['message' => 'Payroll run cannot be cancelled.'], 422);
        }

        $payrollRun->update(['status' => 'cancelled']);

        return (new PayrollRunResource($payrollRun->refresh()))->response();
    }

    public function summary(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
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

    public function export(Request $request, PayrollRun $payrollRun): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        $slips = $payrollRun->paySlips()->with('employee')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="paie_' . $payrollRun->period_start . '.csv"',
        ];

        return response()->streamDownload(function () use ($slips) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for Excel UTF-8 compatibility
            fputs($file, "\xEF\xBB\xBF");
            
            fputcsv($file, [
                'Matricule',
                'Nom',
                'Prénom',
                'Type Salaire',
                'Salaire Brut',
                'Déductions',
                'Salaire Net',
                'Coût Employeur'
            ], ';');

            foreach ($slips as $slip) {
                fputcsv($file, [
                    $slip->employee->matricule ?? '',
                    $slip->employee->last_name ?? '',
                    $slip->employee->first_name ?? '',
                    $slip->employee->salary_type ?? '',
                    number_format((float) $slip->gross_salary, 2, '.', ''),
                    number_format((float) $slip->total_deductions, 2, '.', ''),
                    number_format((float) $slip->net_salary, 2, '.', ''),
                    number_format((float) $slip->total_cost, 2, '.', '')
                ], ';');
            }

            fclose($file);
        }, "paie_" . $payrollRun->period_start . ".csv", $headers);
    }
}
