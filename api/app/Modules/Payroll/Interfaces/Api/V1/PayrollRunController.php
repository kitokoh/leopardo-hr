<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PayrollRunResource;
use App\Jobs\WarmPaySlipPdfPathsForPayrollRunJob;
use App\Modules\Payroll\Domain\Exceptions\PayrollAlreadyValidatedException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Exports\PayrollAccountingExportService;
use App\Modules\Payroll\Infrastructure\Services\PayrollAnomalyService;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;
use App\Modules\Payroll\Infrastructure\Services\PayrollJournalGenerator;
use App\Modules\Payroll\Interfaces\Api\V1\Requests\StorePayrollRunRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
        private readonly PayrollClosingService $closing,
        private readonly DataAccessAuditLogger $dataAccessAuditLogger,
    ) {}

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

    public function store(StorePayrollRunRequest $request): JsonResponse
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

        try {
            // Étape 1 du workflow F-11 : validation RH via le service de clôture
            // (mise à jour conditionnelle atomique + audit trail `payroll_run_validated`).
            $this->closing->validateRh($payrollRun, $actor);
        } catch (PayrollAlreadyValidatedException|\App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Étape 2 : bascule des bulletins en `validated` (transaction propre —
        // une panne ici ne doit pas laisser de bulletins non validés sur un run validé).
        DB::transaction(function () use ($payrollRun): void {
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

        if (in_array($payrollRun->status, ['paid', 'cancelled', 'locked'], true)) {
            return response()->json(['message' => 'Payroll run cannot be cancelled in current status.'], 422);
        }

        $payrollRun->update(['status' => 'cancelled']);

        return (new PayrollRunResource($payrollRun->refresh()))->response();
    }

    /**
     * Étape 2 du workflow F-11 — clôture comptable : verrouille un run validé.
     * Toute modification ultérieure (recalcul, annulation) est refusée tant que
     * le run est verrouillé ; l'opération est tracée (audit `payroll_run_locked`).
     */
    public function lock(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        try {
            $this->closing->lock($payrollRun, $actor);
        } catch (PayrollAlreadyValidatedException|\App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new PayrollRunResource($payrollRun->refresh()->loadCount('paySlips')))->response();
    }

    /**
     * Déverrouillage motivé d'un run clôturé (retour à `validated`).
     * La raison est obligatoire et tracée (audit `payroll_run_unlocked`).
     */
    public function unlock(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->closing->unlock($payrollRun, $actor, $validated['reason']);
        } catch (PayrollRunLockedException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new PayrollRunResource($payrollRun->refresh()->loadCount('paySlips')))->response();
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

    /**
     * F-10 (#1540) : journal de paie mensuel (CSV) — une ligne par bulletin
     * validé + ligne de totaux (contrôle comptable). Régime de preuve horodaté
     * par le run. Réservé aux managers principal/comptable.
     */
    public function journal(Request $request, PayrollRun $payrollRun): StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        $this->dataAccessAuditLogger->recordSensitive($request, $actor, 'hr_data.payroll_journal_viewed', $payrollRun, [
            'resource' => 'payroll_journal',
        ]);

        $filename = 'journal_paie_'.$payrollRun->period_start->toDateString().'_'.$payrollRun->period_end->toDateString().'.csv';

        return response()->streamDownload(function () use ($payrollRun): void {
            echo (new PayrollJournalGenerator)->generate($payrollRun);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * F-20 (#1550) : rapport pré-clôture des anomalies (doublons, bulletins
     * incohérents, variance de brut, écarts pointage → paie). Lecture seule —
     * l'action humaine décide des corrections avant validation/verrouillage.
     */
    public function anomalies(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        $anomalies = (new PayrollAnomalyService)->detectForRun($payrollRun->load('paySlips'));

        return response()->json([
            'data' => [
                'run_id' => $payrollRun->id,
                'total' => count($anomalies),
                'anomalies' => $anomalies,
            ],
        ]);
    }

    public function export(Request $request, PayrollRun $payrollRun, PayrollAccountingExportService $exportService): StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="paie_'.$payrollRun->period_start.'.csv"',
        ];

        return response()->streamDownload(
            $exportService->generateCsvClosure($payrollRun),
            'paie_'.$payrollRun->period_start.'.csv',
            $headers
        );
    }
}
