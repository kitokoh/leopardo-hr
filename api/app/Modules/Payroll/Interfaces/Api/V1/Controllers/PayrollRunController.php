<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PayrollRunResource;
use App\Jobs\WarmPaySlipPdfPathsForPayrollRunJob;
use App\Modules\Payroll\Application\Actions\CalculatePayrollRun;
use App\Modules\Payroll\Application\Actions\CancelPayrollRun;
use App\Modules\Payroll\Application\Actions\CreatePayrollRegularization;
use App\Modules\Payroll\Application\Actions\LockPayrollRun;
use App\Modules\Payroll\Application\Actions\UnlockPayrollRun;
use App\Modules\Payroll\Application\Actions\ValidatePayrollRun;
use App\Modules\Payroll\Application\Services\PayrollRegularizationService;
use App\Modules\Payroll\Domain\Exceptions\PayrollAlreadyValidatedException;
use App\Modules\Payroll\Domain\Exceptions\PayrollPlaceholderAcknowledgementRequiredException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunCalculationFailedException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunCalculationRulesException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunNoSlipsException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunNotLockedException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunZeroSlipsException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Exports\PayrollAccountingExportService;
use App\Modules\Payroll\Infrastructure\Services\PayrollAnomalyService;
use App\Modules\Payroll\Infrastructure\Services\PayrollBordereauGenerator;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\PayrollJournalGenerator;
use App\Modules\Payroll\Interfaces\Api\V1\Requests\StorePayrollRunRequest;
use App\Modules\Payroll\Interfaces\Api\V1\Support\PayrollRunAccess;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
        private readonly CalculatePayrollRun $calculateAction,
        private readonly CancelPayrollRun $cancelAction,
        private readonly PayrollRegularizationService $regularization,
        private readonly DataAccessAuditLogger $auditLogger,
        private readonly ValidatePayrollRun $validateRun,
        private readonly LockPayrollRun $lockRun,
        private readonly UnlockPayrollRun $unlockRun,
        private readonly CreatePayrollRegularization $createRegularization,
        private readonly PayrollRunAccess $access,
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

        // #6552 (audit) : garde anti-doublon de periode - un run (non
        // annule) existe deja pour cette periode → 409 propre. L'index
        // unique partiel en base est le verrou final en cas de course
        // (catch 23505 ci-dessous).
        $existing = PayrollRun::query()
            ->where('company_id', $actor->company_id)
            ->where('period_start', $validated['period_start'])
            ->where('period_end', $validated['period_end'])
            ->where('country_code', $validated['country_code'])
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($existing) {
            return new JsonResponse([
                'error' => 'PAYROLL_RUN_PERIOD_ALREADY_EXISTS',
                'message' => __('errors.PAYROLL_RUN_PERIOD_ALREADY_EXISTS'),
            ], 409);
        }

        try {
            $run = PayrollRun::create([
                'company_id' => $actor->company_id,
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'country_code' => $validated['country_code'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (QueryException $e) {
            // #6552 : course perdue sur l'index unique partiel → meme reponse
            // 409, jamais de 500.
            if ($e->getCode() === '23505') {
                return new JsonResponse([
                    'error' => 'PAYROLL_RUN_PERIOD_ALREADY_EXISTS',
                    'message' => __('errors.PAYROLL_RUN_PERIOD_ALREADY_EXISTS'),
                ], 409);
            }

            throw $e;
        }

        $response = (new PayrollRunResource($run))->response();
        $response->setStatusCode(201);

        // Issue #5623 - pays a baremes 'placeholder' (non valides legalement,
        // ex. BF/ML/TG) : alerter immediatement le manager a la creation.
        $warning = $this->placeholderWarning($validated['country_code']);
        if ($warning !== null) {
            $response->setData((object) [
                ...(array) $response->getData(),
                'warning' => $warning,
            ]);
        }

        return $response;
    }

    /**
     * Issue #5623 - warning structure si les regles de paie du pays sont au
     * niveau 'placeholder' (baremes indicatifs, non valides par un
     * expert-comptable local). Retourne null pour pilot/production/unknown.
     *
     * @return array{code: string, message: string, country: string}|null
     */
    private function placeholderWarning(string $countryCode): ?array
    {
        try {
            $rules = $this->calculator->getRules($countryCode);
        } catch (\Throwable) {
            return null;
        }

        if ($rules->confidenceLevel() !== 'placeholder') {
            return null;
        }

        return [
            'code' => 'PAYROLL_PLACEHOLDER_COUNTRY',
            'message' => __('payroll.compliance_warning_placeholder'),
            'country' => $countryCode,
        ];
    }

    public function show(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->access->authorizeManager($payrollRun, $actor);

        $payrollRun->loadCount('paySlips');

        return (new PayrollRunResource($payrollRun))->response();
    }

    public function calculate(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->access->authorizeManager($payrollRun, $actor);

        // #6529 : un run laisse en `error` ou orphelin en `processing` (worker
        // mort entre le claim et le calcul) doit rester recalculable ;
        // `calculated` reste permis (recalcul correctif) ; les statuts de
        // cloture (validated, paid...) restent exclus.
        if (in_array($payrollRun->status, ['draft', 'calculated', 'error', 'processing'], true) === false) {
            return response()->json(['message' => __('payroll.run_cannot_recalculate')], 422);
        }

        // Cas d'usage nommable (ADR-0020, lot 1b #6968) : regles pays,
        // garde placeholder auditee (#2332/#5623), calcul, invariants #1767 -
        // la politique reste dans PayrollCalculator ; l'interface mappe les
        // exceptions vers les réponses localisées et journalise le détail.
        try {
            $run = $this->calculateAction->execute(
                $payrollRun,
                $actor,
                $request->boolean('acknowledge_placeholder'),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (PayrollRunCalculationRulesException $e) {
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
        } catch (PayrollPlaceholderAcknowledgementRequiredException $e) {
            return response()->json([
                'message' => __('payroll.placeholder_acknowledge_required', ['country' => $e->countryCode]),
                'errors' => [
                    'acknowledge_placeholder' => [__('payroll.placeholder_acknowledge_required', ['country' => $e->countryCode])],
                ],
            ], 422);
        } catch (PayrollRunCalculationFailedException $e) {
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
        } catch (PayrollRunZeroSlipsException) {
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
        // Issue #5246 - workflow de validation RBAC : la validation (verification
        // / vise) est reservee aux managers principal/comptable (contrat
        // documente RBAC_ROUTE_MATRIX.md F-11/#1541). Un `rh` peut PREPARER
        // (calculer) mais pas auto-valider sa propre preparation.
        $this->access->authorizeApprover($payrollRun, $actor);

        // Issue #5623 - baremes 'placeholder' : la validation RH exige une
        // confirmation explicite (les chiffres peuvent etre incorrects).
        if ($this->placeholderWarning($payrollRun->country_code) !== null
            && $request->boolean('confirm_placeholder') === false) {
            return response()->json([
                'error' => 'PAYROLL_PLACEHOLDER_CONFIRM_REQUIRED',
                'message' => 'PAYROLL_PLACEHOLDER_CONFIRM_REQUIRED',
                'localized_message' => __('errors.PAYROLL_PLACEHOLDER_CONFIRM_REQUIRED'),
            ], 422);
        }

        try {
            // Etape 1 du workflow F-11 : validation RH - cas d'usage nommable
            // (ADR-0020, lot 1 #6896). La politique métier (mise à jour
            // conditionnelle atomique + audit `payroll_run_validated`) reste
            // dans PayrollClosingService, encapsulé par l'Action.
            $this->validateRun->execute($payrollRun, $actor);
        } catch (PayrollAlreadyValidatedException|PayrollRunLockedException|PayrollRunNoSlipsException $e) {
            // #3810 / #4310 : codes stables + message localise via catalogue,
            // jamais le message d'exception brut (FR codé en dur, non traduit).
            return $this->access->businessError($e);
        } catch (\RuntimeException $e) {
            return $this->access->unexpectedError('payroll.run.validation_failed', $payrollRun, $e, 'PAYROLL_RUN_VALIDATION_FAILED');
        }

        if (config('performance.payroll.queue_pdf_warmup', true)) {
            WarmPaySlipPdfPathsForPayrollRunJob::dispatch($payrollRun->id);
        }

        return (new PayrollRunResource($payrollRun->refresh()->loadCount('paySlips')))->response();
    }

    public function cancel(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->access->authorizeManager($payrollRun, $actor);

        if (in_array($payrollRun->status, ['paid', 'cancelled', 'locked'], true)) {
            return response()->json(['message' => __('errors.PAYROLL_RUN_CANCEL_NOT_ALLOWED')], 422);
        }

        // Cas d'usage nommable (ADR-0020, lot 1b #6968).
        $payrollRun = $this->cancelAction->execute($payrollRun);

        return (new PayrollRunResource($payrollRun))->response();
    }

    /**
     * Etape 2 du workflow F-11 - cloture comptable : verrouille un run valide.
     * Toute modification ulterieure (recalcul, annulation) est refusee tant que
     * le run est verrouille ; l'opération est tracée (audit `payroll_run_locked`).
     */
    public function lock(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        // Issue #5246 — l'approbation finale (cloture comptable / verrouillage)
        // est reservee aux managers principal/comptable : un `rh` ne peut pas
        // cloturer ce qu'il a préparé (séparation des tâches).
        $this->access->authorizeApprover($payrollRun, $actor);

        try {
            // Etape 2 du workflow F-11 - cas d'usage nommable (ADR-0020, #6896).
            $this->lockRun->execute($payrollRun, $actor);
        } catch (PayrollAlreadyValidatedException|PayrollRunLockedException|PayrollRunNoSlipsException $e) {
            // #3810 / #4310 : codes stables + message localisé via catalogue,
            // jamais le message d'exception brut (FR code en dur, non traduit).
            return $this->access->businessError($e);
            // assertHasPaySlips() (lock) peut jeter une RuntimeException metier.
        } catch (\RuntimeException $e) {
            return $this->access->unexpectedError('payroll.run.lock_failed', $payrollRun, $e, 'PAYROLL_RUN_LOCK_FAILED');
        }

        return (new PayrollRunResource($payrollRun->refresh()->loadCount('paySlips')))->response();
    }

    /**
     * Deverrouillage motive d'un run clôturé (retour à `validated`).
     * La raison est obligatoire et tracée (audit `payroll_run_unlocked`).
     */
    public function unlock(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        // Issue #5246 — la réversion d'une cloture est une decision sensible :
        // reservee aux managers principal/comptable (meme regle que lock).
        $this->access->authorizeApprover($payrollRun, $actor);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            // Cas d'usage nommable (ADR-0020, lot 1 #6896).
            $this->unlockRun->execute($payrollRun, $actor, $validated['reason']);
        } catch (PayrollRunLockedException $e) {
            // #3810 / #4310 : codes stables + message localise via catalogue.
            return $this->access->businessError($e);
            // unlock() jette des RuntimeException metier (run non verrouille,
            // raison manquante).
        } catch (\RuntimeException $e) {
            return $this->access->unexpectedError('payroll.run.unlock_failed', $payrollRun, $e, 'PAYROLL_RUN_UNLOCK_FAILED');
        }

        return (new PayrollRunResource($payrollRun->refresh()->loadCount('paySlips')))->response();
    }

    /**
     * DZ-DEPTH (#1818) - cree un run de regularisation pour un run verrouille.
     * Le run original n'est jamais modifié ; le motif est obligatoire et tracé.
     */
    public function regularize(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->access->authorizeManager($payrollRun, $actor);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            // Cas d'usage nommable (ADR-0020, lot 1 #6896) - DZ-DEPTH #1818.
            /** @var PayrollRun $regularization */
            $regularization = $this->createRegularization->execute(
                $payrollRun,
                $actor,
                (string) $validated['reason'],
            );
        } catch (PayrollRunNotLockedException $e) {
            // #3810 / #4310 : codes stables + message localise via catalogue.
            return $this->access->businessError($e);
        } catch (\RuntimeException $e) {
            return $this->access->unexpectedError('payroll.run.regularization_failed', $payrollRun, $e, 'PAYROLL_REGULARIZATION_FAILED');
        }

        return (new PayrollRunResource($regularization->loadCount('paySlips')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DZ-DEPTH (#1818) - liste les regularisations liees a un run.
     */
    public function regularizations(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->access->authorizeManager($payrollRun, $actor);

        $runs = $this->regularization->regularizations($payrollRun);

        return PayrollRunResource::collection($runs)->response();
    }

    public function summary(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->access->authorizeManager($payrollRun, $actor);

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
     * F-10 (#1540) : journal de paie mensuel (CSV) - une ligne par bulletin
     * valide + ligne de totaux (controle comptable). Regime de preuve horodate
     * par le run. Reserve aux managers principal/comptable.
     */
    public function journal(Request $request, PayrollRun $payrollRun): StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->access->authorizeManager($payrollRun, $actor);

        $this->auditLogger->recordSensitive($request, $actor, 'payroll.journal', $payrollRun);

        $filename = 'journal_paie_'.$payrollRun->period_start->toDateString().'_'.$payrollRun->period_end->toDateString().'.csv';

        return response()->streamDownload(function () use ($payrollRun): void {
            echo (new PayrollJournalGenerator)->generate($payrollRun);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * #5243 - Bordereau de paie d'un run (totaux par cotisation + récapitulatif) :
     * CSV téléchargeable, réservé aux managers, garde pays DZ (422 sinon) et
     * isolation tenant (404), comme les autres documents par run.
     */
    public function bordereau(Request $request, PayrollRun $payrollRun): StreamedResponse|JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->access->authorizeManager($payrollRun, $actor);

        if ($payrollRun->country_code !== 'DZ') {
            return response()->json([
                'message' => __('errors.PAYROLL_RUN_NOT_FOR_COUNTRY', ['country' => 'l’Algérie (DZ)']),
            ], 422);
        }

        $this->auditLogger->recordSensitive($request, $actor, 'payroll.bordereau', $payrollRun);

        $filename = 'bordereau_paie_'.$payrollRun->period_start->toDateString().'_'.$payrollRun->period_end->toDateString().'.csv';

        return response()->streamDownload(function () use ($payrollRun): void {
            echo (new PayrollBordereauGenerator)->generate($payrollRun);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * F-20 (#1550) : rapport pre-cloture des anomalies (doublons, bulletins
     * incoherents, variance de brut, ecarts pointage → paie). Lecture seule -
     * l'action humaine décide des corrections avant validation/verrouillage.
     */
    public function anomalies(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->access->authorizeManager($payrollRun, $actor);

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
        $this->access->authorizeManager($payrollRun, $actor);

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
