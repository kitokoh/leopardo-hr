<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PayrollRunResource;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use App\Modules\Payroll\Infrastructure\Services\PayrollCycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Plan 61 - cycles de paie et solde employe mobile-first.
 */
class PayrollCycleController extends Controller
{
    public function __construct(private readonly PayrollCycleService $cycleService) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $runs = PayrollRun::query()
            ->where('company_id', $actor->company_id)
            ->orderByDesc('period_start')
            ->paginate(max(1, min(100, max(1, min(100, $request->integer('per_page', 15))))));

        // PA2-API-001: this endpoint used to return Laravel's raw paginator
        // shape (top-level current_page/data/links/...), which diverges from
        // the success/data/meta envelope used by every other paginated list
        // in the API (see PayrollRunController::index, EmployeeController::index,
        // etc). Route through the same resource used by /payroll-runs so
        // /payroll/cycles matches the standard contract.
        return PayrollRunResource::collection($runs)->response();
    }

    /**
     * PA2-PAY-011 — GET /payroll/cycle-settings: expose the company's
     * configurable pay cycle rule (daily/weekly/monthly, pay day, week start)
     * to managers so they can review it before changing it.
     */
    public function cycleSettings(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $company = $actor->company;
        if (! $company instanceof Company) {
            abort(404);
        }

        return response()->json([
            'data' => $this->cycleService->getPayCycleSettings($company),
        ]);
    }

    /**
     * PA2-PAY-011 — PUT /payroll/cycle-settings: let a manager configure the
     * company-wide pay cycle rule (journalier/hebdomadaire/mensuel), pay day,
     * and week start. Applies immediately to getCurrentCycle()/balances.
     */
    public function updateCycleSettings(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $company = $actor->company;
        if (! $company instanceof Company) {
            abort(404);
        }

        $validated = $request->validate([
            'pay_cycle' => ['sometimes', 'string', 'in:daily,weekly,monthly'],
            'pay_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'week_start' => ['sometimes', 'integer', 'min:1', 'max:7'],
        ]);

        $settings = $this->cycleService->updatePayCycleSettings($company, $validated);

        return response()->json([
            'data' => $settings,
            'message' => __('payroll.cycle_updated'),
        ]);
    }

    /**
     * PA2-PAY-003 — GET /payroll/cycles/preview: lets a manager preview the
     * resulting period and an estimated payroll total for a candidate pay
     * cycle rule (journalier/hebdomadaire/mensuel, pay day, week start)
     * before committing it via PUT /payroll/cycle-settings. Read-only, does
     * not persist anything.
     */
    public function preview(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $company = $actor->company;
        if (! $company instanceof Company) {
            abort(404);
        }

        $overrides = $request->validate([
            'pay_cycle' => ['sometimes', 'string', 'in:daily,weekly,monthly'],
            'pay_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'week_start' => ['sometimes', 'integer', 'min:1', 'max:7'],
        ]);

        return response()->json([
            'data' => $this->cycleService->previewCycle($company, $overrides),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $company = $actor->company;
        if (! $company instanceof Company) {
            abort(404);
        }

        $cycle = $this->cycleService->getCurrentCycle($company);
        $payload = [
            'period_start' => $cycle['start']->toDateString(),
            'period_end' => $cycle['end']->toDateString(),
            'label' => $cycle['label'],
        ];

        // Issue #4500 : forme de réponse alignée sur myBalance et les autres
        // endpoints payroll — {data: {...}} uniquement (le `+ $payload`
        // aplatissait toutes les clés au niveau racine, contrat incohérent).
        return response()->json(['data' => $payload]);
    }

    public function myBalance(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        // Issue #2144 — bloc compliance (niveau de confiance paie) exposé
        // au mobile employee : le client affiche l'avertissement localisé.
        return response()->json([
            'data' => $this->cycleService->getEmployeeBalance($actor) + [
                'compliance' => $this->complianceFor($actor),
            ],
        ]);
    }

    public function employeeBalance(Request $request, int $employee): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->id !== $employee && ! $actor->isManager()) {
            abort(403);
        }

        /** @var Employee|null $targetEmployee */
        $targetEmployee = Employee::query()
            ->where('company_id', $actor->company_id)
            ->find($employee);

        if ($targetEmployee === null) {
            abort(404);
        }

        $payload = $this->cycleService->getEmployeeBalance($targetEmployee);

        // Issue #4500 : forme de réponse alignée sur myBalance et les autres
        // endpoints payroll — {data: {...}} uniquement (le `+ $payload`
        // aplatissait toutes les clés au niveau racine, contrat incohérent).
        return response()->json(['data' => $payload]);
    }

    public function mobileSummary(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $items = $this->cycleService->getMobileSummary(
            actor: $actor,
            limit: $request->integer('limit', 50),
        );

        return response()->json([
            'data' => [
                'items' => $items,
                'totals' => [
                    'gross_due' => round(array_sum(array_column($items, 'gross_due')), 2),
                    'advances' => round(array_sum(array_column($items, 'advances')), 2),
                    'paid' => round(array_sum(array_column($items, 'paid')), 2),
                    'remaining' => round(array_sum(array_column($items, 'remaining')), 2),
                    // PA2-PAY-010: expose team-wide overtime hours/pay so the
                    // manager dashboard's "heures supp" acceptance criterion
                    // is satisfied at the summary level, not just per employee.
                    'overtime_hours' => round(array_sum(array_column($items, 'overtime_hours')), 2),
                    'overtime_pay' => round(array_sum(array_column($items, 'overtime_pay')), 2),
                ],
                // Issue #2144 — bloc compliance paie (niveau de confiance +
                // avertissement localisé) pour l'écran paie mobile manager.
                'compliance' => $this->complianceFor($actor),
            ],
        ]);
    }

    /**
     * Issue #2144 — résout le bloc compliance de l'entreprise (même shape
     * que PayrollCalculationPresenter : level/warning/warning_key/source/
     * verification_date). Fail-open : `[]` si le pays n'est pas résoluble —
     * le résumé paie ne doit jamais casser sur un bloc informatif.
     *
     * @return array{level?: string, warning?: string, warning_key?: string, source?: string, verification_date?: string|null}
     */
    private function complianceFor(Employee $actor): array
    {
        try {
            $countryCode = null;

            if (app()->bound('current_company')) {
                $company = currentCompany();
                $countryCode = is_string($company->country ?? null) ? $company->country : null;
            }

            if ($countryCode === null && DB::getDriverName() === 'pgsql') {
                $countryCode = DB::table('public.companies')
                    ->where('id', $actor->company_id)
                    ->value('country');
            }

            if (! is_string($countryCode) || $countryCode === '') {
                return [];
            }

            $rules = app(CountryRulesResolver::class)->resolve($countryCode, (string) $actor->company_id);

            return [
                'level' => $rules->confidenceLevel(),
                'warning' => $rules->complianceWarning(),
                'warning_key' => 'payroll.compliance_warning_'.$rules->confidenceLevel(),
                'source' => $rules->complianceSource(),
                'verification_date' => $rules->verificationDate(),
            ];
        } catch (\Throwable $exception) {
            // Ne jamais masquer silencieusement l'état de conformité (T132) :
            // l'erreur est tracée pour l'observabilité, le front reçoit un
            // état vide explicite.
            Log::warning('payroll.compliance_for_failed', [
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
