<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PayrollRunResource;
use App\Jobs\WarmPaySlipPdfPathsForPayrollRunJob;
use App\Modules\Payroll\Application\Services\PayrollRegularizationService;
use App\Modules\Payroll\Domain\Exceptions\PayrollAlreadyValidatedException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunNoSlipsException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunNotLockedException;
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
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
        private readonly PayrollClosingService $closing,
        private readonly PayrollRegularizationService $regularization,
        private readonly DataAccessAuditLogger $auditLogger,
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

        $runs = $query->orderByDesc('period_start')->paginate(max(1, min(100, $request->integer('per_page', 15))));

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

        // Issue #2555 — un pays sans règles enregistrées (ex. 'ZZ') fait
        // lever `UnsupportedCountryRulesException` ici, AVANT le try/catch :
        // le run restait bloqué dans son statut précédent (ex. `calculated`)
        // et n'était plus recalculable. Contrat : tout échec de calculate
        // ramène le run à `draft` (recalculable), même l'échec de résolution
        // des règles.
        try {
            $rules = $this->calculator->getRules($payrollRun->country_code);
        } catch (\Throwable $e) {
            $payrollRun->update(['status' => PayrollRun::STATUS_DRAFT]);
            Log::error('payroll.run.calculation_failed', [
                'run_id' => $payrollRun->id,
                'company_id' => $payrollRun->company_id,
                'country_code' => $payrollRun->country_code,
                'period' => $payrollRun->period_start->toDateString(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('payroll.calculation_failed'),
            ], 422);
        }

        // Issue #2332 — un pays en règle « placeholder » (aucune valeur légale
        // implémentée) expose des montants indicatifs : un run RÉEL ne doit
        // pas être calculé sans confirmation explicite. Même garde que les
        // simulations (#1872), placée AVANT tout changement de statut pour
        // ne jamais laisser le run bloqué en `calculating` sur un 422.
        // (getRules est déjà résolu ci-dessus — ne pas re-résoudre.)
        if ($rules->confidenceLevel() === 'placeholder') {
            $acknowledged = $request->boolean('acknowledge_placeholder');
            if (! $acknowledged) {
                return response()->json([
                    'message' => __('payroll.placeholder_acknowledge_required', ['country' => $payrollRun->country_code]),
                    'errors' => [
                        'acknowledge_placeholder' => [__('payroll.placeholder_acknowledge_required', ['country' => $payrollRun->country_code])],
                    ],
                ], 422);
            }

            // Acceptation AUDITÉE — mêmes champs que les simulations #1872,
            // contexte `payroll_run_calculate` + run_id pour tracer le run.
            AuditLog::create([
                'company_id' => $payrollRun->company_id,
                'user_id' => $actor->id,
                'action' => 'placeholder_warning_acknowledged',
                'auditable_type' => 'App\\Modules\\Payroll\\Infrastructure\\Services\\CountryRules\\CountryRulesResolver',
                'auditable_id' => 0,
                'old_values' => [],
                'new_values' => [
                    'country_code' => $payrollRun->country_code,
                    'rules_identifier' => (new \ReflectionClass($rules))->getShortName(),
                    'confidence_level' => 'placeholder',
                    'context' => 'payroll_run_calculate',
                    'run_id' => $payrollRun->id,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        $payrollRun->update(['status' => 'calculating']);

        try {
            $run = $this->calculator->calculateRun($payrollRun);
        } catch (\Throwable $e) {
            // Issue #2221 : un échec de calcul ne doit jamais laisser le run
            // bloqué en `calculating` (recalcul refusé à vie par la garde
            // ci-dessus). On restaure `draft` et on journalise le détail.
            $payrollRun->update(['status' => PayrollRun::STATUS_DRAFT]);
            Log::error('payroll.run.calculation_failed', [
                'run_id' => $payrollRun->id,
                'company_id' => $payrollRun->company_id,
                'country_code' => $payrollRun->country_code,
                'period' => $payrollRun->period_start->toDateString(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('payroll.calculation_failed'),
            ], 422);
        }

        // Issue #1767 : un calcul à 0 bulletin (ex. aucune structure salariale
        // active pour ce pays) ne doit pas réussir en silence — sinon le run
        // peut être validé/verrouillé à vide (clôture comptable à zéro).
        if ((int) $run->employee_count === 0) {
            $run->update(['status' => PayrollRun::STATUS_DRAFT]);

            return response()->json([
                'message' => __('payroll.zero_slips_generated'),
            ], 422);
        }

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
        } catch (PayrollAlreadyValidatedException|PayrollRunLockedException|PayrollRunNoSlipsException $e) {
            // #3810 / #4310 : codes stables + message localisé via catalogue,
            // jamais le message d'exception brut (FR codé en dur, non traduit).
            return response()->json([
                'error' => $e->errorCode(),
                'message' => $e->errorCode(),
                'localized_message' => __('errors.'.$e->errorCode()),
            ], $e->statusCode());
        } catch (\RuntimeException $e) {
            Log::error('payroll.run.validation_failed', ['run_id' => $payrollRun->id, 'error' => $e->getMessage()]);

            return response()->json([
                'error' => 'PAYROLL_RUN_VALIDATION_FAILED',
                'message' => 'PAYROLL_RUN_VALIDATION_FAILED',
                'localized_message' => __('errors.PAYROLL_RUN_VALIDATION_FAILED'),
            ], 422);
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
            return response()->json(['message' => __('errors.PAYROLL_RUN_CANCEL_NOT_ALLOWED')], 422);
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
        } catch (PayrollAlreadyValidatedException|PayrollRunLockedException|PayrollRunNoSlipsException $e) {
            // #3810 / #4310 : codes stables + message localisé via catalogue,
            // jamais le message d'exception brut (FR codé en dur, non traduit).
            return response()->json([
                'error' => $e->errorCode(),
                'message' => $e->errorCode(),
                'localized_message' => __('errors.'.$e->errorCode()),
            ], $e->statusCode());
            // assertHasPaySlips() (lock) jette RuntimeException au runtime — le flow
            // analysis PHPStan ne le voit pas à travers DB::transaction().
            // @phpstan-ignore-next-line catch.neverThrown
        } catch (\RuntimeException $e) {
            Log::error('payroll.run.lock_failed', ['run_id' => $payrollRun->id, 'error' => $e->getMessage()]);

            return response()->json([
                'error' => 'PAYROLL_RUN_LOCK_FAILED',
                'message' => 'PAYROLL_RUN_LOCK_FAILED',
                'localized_message' => __('errors.PAYROLL_RUN_LOCK_FAILED'),
            ], 422);
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
        } catch (PayrollRunLockedException $e) {
            // #3810 / #4310 : codes stables + message localisé via catalogue.
            return response()->json([
                'error' => $e->errorCode(),
                'message' => $e->errorCode(),
                'localized_message' => __('errors.'.$e->errorCode()),
            ], $e->statusCode());
            // unlock() jette des RuntimeException métier (run non verrouillé,
            // raison manquante) — le flow analysis PHPStan ne les voit pas.
            // @phpstan-ignore-next-line catch.neverThrown
        } catch (\RuntimeException $e) {
            Log::error('payroll.run.unlock_failed', ['run_id' => $payrollRun->id, 'error' => $e->getMessage()]);

            return response()->json([
                'error' => 'PAYROLL_RUN_UNLOCK_FAILED',
                'message' => 'PAYROLL_RUN_UNLOCK_FAILED',
                'localized_message' => __('errors.PAYROLL_RUN_UNLOCK_FAILED'),
            ], 422);
        }

        return (new PayrollRunResource($payrollRun->refresh()->loadCount('paySlips')))->response();
    }

    /**
     * DZ-DEPTH (#1818) — crée un run de régularisation pour un run verrouillé.
     * Le run original n'est jamais modifié ; le motif est obligatoire et tracé.
     */
    public function regularize(Request $request, PayrollRun $payrollRun): JsonResponse
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
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            /** @var PayrollRun $regularization */
            $regularization = $this->regularization->createRegularization(
                $payrollRun,
                $actor,
                (string) $validated['reason'],
            );
        } catch (PayrollRunNotLockedException $e) {
            // #3810 / #4310 : codes stables + message localisé via catalogue.
            return response()->json([
                'error' => $e->errorCode(),
                'message' => $e->errorCode(),
                'localized_message' => __('errors.'.$e->errorCode()),
            ], $e->statusCode());
        } catch (\RuntimeException $e) {
            Log::error('payroll.run.regularization_failed', ['run_id' => $payrollRun->id, 'error' => $e->getMessage()]);

            return response()->json([
                'error' => 'PAYROLL_REGULARIZATION_FAILED',
                'message' => 'PAYROLL_REGULARIZATION_FAILED',
                'localized_message' => __('errors.PAYROLL_REGULARIZATION_FAILED'),
            ], 422);
        }

        return (new PayrollRunResource($regularization->loadCount('paySlips')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DZ-DEPTH (#1818) — liste les régularisations liées à un run.
     */
    public function regularizations(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        $runs = $this->regularization->regularizations($payrollRun);

        return PayrollRunResource::collection($runs)->response();
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

        $this->auditLogger->recordSensitive($request, $actor, 'payroll.journal', $payrollRun);

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
