<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Exceptions\CountryRulesContextMismatchException;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculationAuditRecorder;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculationPresenter;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Simulation de cotisations sociales et d'impôt sur le revenu.
 *
 * Issue #1782 : ce contrôleur ne duplique PLUS aucune table de taux.
 * La source de vérité unique est le moteur de paie
 * (`PayrollCalculator::getRules()` → `CountryRulesInterface`), qui résout
 * DZ, MA, TN, FR, TR, SN, CEMAC×6, CEDEAO×6 et CA avec les mêmes règles que
 * les vrais bulletins — taux, caps, barèmes et abattements compris.
 *
 * Issue #1869 : la simulation et le bulletin passent par le MÊME noyau de
 * calcul (`PayrollCalculator::computeNetBreakdown()`), ce qui garantit des
 * résultats identiques pour un même brut et un même contexte de règles.
 * La réponse expose :
 *   - au niveau racine, les champs historiques (rétro-compatibles) ;
 *   - sous `contract`, le contrat complet et explicable (pays, devise,
 *     identifiant/version des règles, période, politique d'arrondi,
 *     bracket_tax, retenues totales…) — docs/payroll/CALCULATION_CONTRACT.md.
 */
class CotisationSimulationController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $payrollCalculator,
        private readonly PayrollCalculationPresenter $presenter,
        private readonly PayrollCalculationAuditRecorder $auditRecorder,
    ) {}

    public function simulate(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'gross_salary' => 'required|numeric|min:0',
            // #1951 : contrat partagé du moteur (plus de liste in: hardcodée).
            'country_code' => ['required', 'string', Rule::in($this->payrollCalculator->rulesResolver()->supportedCountryCodes())],
            'rules_period' => ['nullable', 'date'],
            // Issue #1872 — une règle « placeholder » (aucune valeur légale
            // implémentée) exige une confirmation explicite.
            'acknowledge_placeholder' => ['nullable', 'boolean'],
        ]);

        /** @var array{gross_salary: float|string, country_code: string, rules_period?: string|null, acknowledge_placeholder?: bool} $validated */
        $gross = (float) $validated['gross_salary'];
        $countryCode = $validated['country_code'];
        $rulesPeriodValue = $validated['rules_period'] ?? null;
        $rulesPeriod = $rulesPeriodValue !== null ? Carbon::parse($rulesPeriodValue) : null;

        // Issue #1874 — identifiant de corrélation de la requête (logs ↔
        // réponse ↔ audit) : X-Correlation-ID / X-Request-Id header (repli
        // UUID frais), propagé aux logs et à la réponse (RequestIdMiddleware).
        $correlationId = correlation_id();
        Log::withContext(['correlation_id' => $correlationId]);

        // Pays inconnu → 422 explicite (UnsupportedCountryRulesException,
        // rendue par le handler d'exceptions avec un message métier clair).
        // Issue #1924/#1871 — le tenant et la période effective sont transmis
        // afin que les overrides entreprise et les règles historiques soient
        // identiques à ceux appliqués par un bulletin réel.
        $companyId = (string) $actor->company_id;
        $rules = $this->resolveRules($correlationId, $companyId, $countryCode, $gross, $rulesPeriod);

        // Issue #1872 — les règles « placeholder » (BJ/TG/NE/CF/TD/GQ : aucune
        // valeur légale sourcée) ne peuvent pas alimenter une simulation sans
        // confirmation explicite ; l'acceptation est AUDITÉE (tenant, pays,
        // acteur) — jamais de secrets ni de données biométriques.
        if ($rules->confidenceLevel() === 'placeholder') {
            $acknowledged = $request->boolean('acknowledge_placeholder');
            if (! $acknowledged) {
                return response()->json([
                    'message' => __('payroll.placeholder_acknowledge_required', ['country' => $countryCode]),
                    'errors' => [
                        'acknowledge_placeholder' => [__('payroll.placeholder_acknowledge_required', ['country' => $countryCode])],
                    ],
                ], 422);
            }

            AuditLog::create([
                'company_id' => $actor->company_id,
                'user_id' => $actor->id,
                'action' => 'placeholder_warning_acknowledged',
                'auditable_type' => 'App\Modules\Payroll\Infrastructure\Services\CountryRules\CountryRulesResolver',
                'auditable_id' => 0,
                'old_values' => [],
                'new_values' => [
                    'country_code' => $countryCode,
                    'rules_identifier' => (new \ReflectionClass($rules))->getShortName(),
                    'confidence_level' => 'placeholder',
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        // Issue #1869 — mêmes appels métier que PayrollCalculator::calculateSlip().
        $breakdown = $this->payrollCalculator->computeNetBreakdown($gross, $rules);
        $social = $breakdown['social'];

        $employeeContributions = [];
        $employerContributions = [];
        foreach ($rules->socialContributions() as $contribution) {
            // Issue #2220 — la base suit la VRAIE règle du moteur :
            //  1. assiette_rate (ex. CSG/CRDS FR sur 98,25 % du brut) ;
            //  2. tranche floor/ceiling (ex. IPRES T2 SN 432 001–2 160 000) ;
            //  3. cap simple (plafond classique) ;
            //  4. sinon brut entier.
            if (isset($contribution['assiette_rate'])) {
                $base = $gross * ((float) $contribution['assiette_rate'] / 100);
            } elseif (isset($contribution['floor'])) {
                $base = $gross > (float) $contribution['floor']
                    ? min($gross, (float) ($contribution['ceiling'] ?? PHP_FLOAT_MAX)) - (float) $contribution['floor']
                    : 0.0;
            } else {
                $base = ($contribution['cap'] ?? null) === null
                    ? $gross
                    : min($gross, (float) $contribution['cap']);
            }

            $item = [
                'name' => $contribution['name'],
                'code' => $contribution['code'],
                'rate' => $contribution['rate'],
                'cap' => $contribution['cap'] ?? null,
                'amount' => round($base * (float) $contribution['rate'] / 100, 2),
            ];

            if ($contribution['type'] === 'employee') {
                $employeeContributions[] = $item;
            } else {
                $employerContributions[] = $item;
            }
        }

        // Issue #1874 — audit de la simulation (résultats agrégés uniquement,
        // jamais de salaires individuels ni de secrets).
        $this->auditRecorder->recordSimulation(
            $correlationId,
            $companyId,
            $countryCode,
            ['gross_salary' => $gross, 'rules_period' => $rulesPeriod?->toDateString()],
            [
                'total_employee_deduction' => round($social['employee'], 2),
                'total_employer_cost' => round($social['employer'], 2),
                'income_tax' => $breakdown['income_tax'],
                'bracket_tax' => $breakdown['bracket_tax'],
                'total_deductions' => round($breakdown['base_deductions'], 2),
                'net_salary' => $breakdown['net_salary'],
                'total_cost_employer' => $breakdown['total_cost'],
            ],
            PayrollCalculationAudit::STATUS_SUCCESS,
            null,
            $rules->rulesVersion(),
            (new \ReflectionClass($rules))->getShortName(),
        );

        return response()->json([
            'data' => [
                // Issue #1874 — corrélation requête ↔ logs ↔ audit.
                'correlation_id' => $correlationId,
                // ── Champs historiques (rétro-compatibles) ───────────────────
                'country_code' => $countryCode,
                'gross_salary' => $gross,
                'employee_contributions' => $employeeContributions,
                'employer_contributions' => $employerContributions,
                'total_employee_deduction' => $social['employee'],
                'total_employer_cost' => $social['employer'],
                'taxable_gross' => round($breakdown['taxable_gross'], 2),
                'income_tax' => $breakdown['income_tax'],
                'bracket_tax' => $breakdown['bracket_tax'],
                'total_deductions' => round($breakdown['base_deductions'], 2),
                // Rétro-compatible : brut − cotisations salariales (sans impôt).
                'net_before_tax' => round($gross - $social['employee'], 2),
                // Net réel = brut − retenues totales (issue #1782 + #1869).
                'net_salary' => $breakdown['net_salary'],
                'total_cost_employer' => $breakdown['total_cost'],
                // ── Contrat complet et explicable (issue #1869) ──────────────
                // Le contrat reflète les overrides entreprise et la période
                // effective, comme le bulletin réel.
                'contract' => $this->presenter->present(
                    $countryCode,
                    $gross,
                    $companyId,
                    $rulesPeriod
                ),
            ],
        ]);
    }

    /**
     * Résout les règles pays pour la simulation ; toute erreur de résolution
     * est tracée dans l'audit (rule_missing / validation_error /
     * provider_error) puis relancée — la réponse HTTP reste inchangée.
     */
    private function resolveRules(
        string $correlationId,
        string $companyId,
        string $countryCode,
        float $gross,
        ?Carbon $rulesPeriod
    ): CountryRulesInterface {
        $input = ['gross_salary' => $gross, 'rules_period' => $rulesPeriod?->toDateString()];

        try {
            return $this->payrollCalculator->rulesResolver()->resolve($countryCode, $companyId, $rulesPeriod);
        } catch (UnsupportedCountryRulesException $exception) {
            $this->auditRecorder->recordSimulation(
                $correlationId,
                $companyId,
                $countryCode,
                $input,
                null,
                PayrollCalculationAudit::STATUS_RULE_MISSING
            );

            throw $exception;
        } catch (CountryRulesContextMismatchException $exception) {
            $this->auditRecorder->recordSimulation(
                $correlationId,
                $companyId,
                $countryCode,
                $input,
                null,
                PayrollCalculationAudit::STATUS_VALIDATION_ERROR
            );

            throw $exception;
        } catch (\Throwable $exception) {
            $this->auditRecorder->recordSimulation(
                $correlationId,
                $companyId,
                $countryCode,
                $input,
                null,
                PayrollCalculationAudit::STATUS_PROVIDER_ERROR
            );

            throw $exception;
        }
    }
}
