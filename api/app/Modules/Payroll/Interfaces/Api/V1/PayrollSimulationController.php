<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AbstractCountryRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculationAuditRecorder;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Issue #1814 — Simulation d'impact d'un barème fiscal (dry-run).
 *
 * POST /api/v1/payroll/simulate (manager principal/comptable) et
 * POST /api/v1/admin/payroll/simulate (platform_admin).
 *
 * Ne persiste RIEN : exécute le moteur de paie réel
 * (CountryRulesInterface via PayrollCalculator) avec un barème fourni en
 * paramètre (`slabs_override`), ou le barème actuel s'il est absent.
 * La réponse détaille le calcul ligne par ligne (cotisations, assiette,
 * impôt par tranche, net, coût employeur).
 */
class PayrollSimulationController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $payrollCalculator,
        private readonly PayrollCalculationAuditRecorder $auditRecorder,
    ) {}

    public function simulate(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Employee) {
            if (! $user->isManager()) {
                abort(403);
            }
        } elseif (! $user instanceof SuperAdmin) {
            abort(401);
        }

        $validated = $request->validate([
            'gross_salary' => ['required', 'numeric', 'min:0'],
            // #1951 : contrat partagé — mêmes pays que le moteur de paie
            // (plus de liste in: hardcodée, divergence #1951).
            'country_code' => ['required', 'string', Rule::in($this->payrollCalculator->rulesResolver()->supportedCountryCodes())],
            'slabs_override' => ['sometimes', 'array', 'min:1'],
            'slabs_override.*.min' => ['required_with:slabs_override', 'numeric', 'min:0'],
            'slabs_override.*.max' => ['nullable', 'numeric', 'min:0'],
            'slabs_override.*.rate' => ['required_with:slabs_override', 'numeric', 'min:0', 'max:100'],
            'slabs_override.*.fixed_deduction' => ['sometimes', 'numeric', 'min:0'],
            'ignore_caps' => ['sometimes', 'boolean'],
            // Issue #1872 — règle « placeholder » : confirmation explicite requise.
            'acknowledge_placeholder' => ['sometimes', 'boolean'],
        ]);

        /** @var array{gross_salary: float|string, country_code: string, slabs_override?: array<int, array{min: float|string, max?: float|string|null, rate: float|string, fixed_deduction?: float|string}>, ignore_caps?: bool} $validated */
        $gross = (float) $validated['gross_salary'];
        $countryCode = $validated['country_code'];

        // Issue #1874 — identifiant de corrélation de la requête (logs ↔
        // réponse ↔ audit) : X-Correlation-ID / X-Request-Id header (repli
        // UUID frais), propagé aux logs et à la réponse (RequestIdMiddleware).
        $correlationId = correlation_id();
        Log::withContext(['correlation_id' => $correlationId]);

        $companyId = $user instanceof Employee ? (string) $user->company_id : null;
        $rules = $this->resolveRules($correlationId, $companyId, $countryCode, $gross, isset($validated['slabs_override']));

        // Issue #1872 — règle « placeholder » : simulation indicative
        // interdite sans confirmation explicite (jamais de présentation
        // comme bulletin certifié) ; l'acceptation est AUDITÉE.
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

            $companyId = $user instanceof Employee ? $user->company_id : null;
            if ($companyId !== null) {
                AuditLog::create([
                    'company_id' => $companyId,
                    'user_id' => $user->id,
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
        }

        // Override dry-run du barème (non persistant).
        if (isset($validated['slabs_override'])) {
            $slabs = array_map(static fn (array $slab): array => [
                'min' => (float) $slab['min'],
                'max' => ($slab['max'] ?? null) !== null ? (float) $slab['max'] : null,
                'rate' => (float) $slab['rate'],
                'fixed_deduction' => (float) ($slab['fixed_deduction'] ?? 0),
            ], $validated['slabs_override']);

            $rules->withTaxSlabs($slabs);
        }

        // Issue #1815 : comparaison « avec/sans plafond légal ». La méthode
        // vit sur AbstractCountryRules (pas sur le contrat) — garde instanceof.
        if ($rules instanceof AbstractCountryRules) {
            $rules->withCapsEnabled(! (bool) ($validated['ignore_caps'] ?? false));
        }

        // Issue #2220 — parité simulation/bulletin : le net, l'impôt et les
        // cotisations passent par le pipeline UNIQUE utilisé par les
        // bulletins (computeNetBreakdown), y compris la taxe de minimum
        // fiscal (TRIMF SN via combineMinimumFiscalTax) — plus de divergence
        // simulation vs bulletin (#1869).
        $breakdown = $this->payrollCalculator->computeNetBreakdown($gross, $rules);
        $social = $breakdown['social'];
        $taxBase = round($breakdown['taxable_gross'], 2);
        $incomeTax = $breakdown['income_tax'];
        $netSalary = $breakdown['net_salary'];
        $totalCost = $breakdown['total_cost'];

        // Impôt par tranche : convention mensuelle OU annualisée selon la
        // règle pays — le total converge vers l'impôt du moteur (#2220).
        $bySlab = $this->payrollCalculator->slabTaxBreakdown($rules, $gross, $taxBase, $incomeTax);

        // Issue #1874 — audit de la simulation (résultats agrégés uniquement).
        $this->auditRecorder->recordSimulation(
            $correlationId,
            $companyId,
            $countryCode,
            ['gross_salary' => $gross, 'has_slabs_override' => isset($validated['slabs_override'])],
            [
                'social_employee' => round($social['employee'], 2),
                'social_employer' => round($social['employer'], 2),
                'tax_base' => round($taxBase, 2),
                'income_tax' => round($incomeTax, 2),
                'net' => $netSalary,
                'total_cost' => $totalCost,
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
                'gross' => $gross,
                'country_code' => $countryCode,
                // Issue #1872 — conformité : niveau de confiance des règles
                // pays + avertissement localisé + source légale + date de
                // vérification experte (même structure que le contrat du
                // PayrollCalculationPresenter, consommée par TaxSlabsView).
                'compliance' => [
                    'level' => $rules->confidenceLevel(),
                    'warning' => $rules->complianceWarning(),
                    'warning_key' => 'payroll.compliance_warning_'.$rules->confidenceLevel(),
                    'source' => $rules->complianceSource(),
                    'verification_date' => $rules->verificationDate(),
                ],
                'social_employee' => $social['employee'],
                'social_employer' => $social['employer'],
                'tax_base' => $taxBase,
                'income_tax' => $incomeTax,
                'income_tax_by_slab' => $bySlab,
                'net' => $netSalary,
                'total_cost' => $totalCost,
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
        ?string $companyId,
        string $countryCode,
        float $gross,
        bool $hasSlabsOverride
    ): CountryRulesInterface {
        $input = ['gross_salary' => $gross, 'has_slabs_override' => $hasSlabsOverride];

        try {
            // Appel direct au résolveur (comme CotisationSimulationController) :
            // `getRules()` masque l'exception de contexte pour PHPStan (dead catch
            // catch.neverThrown) alors que `resolve()` la déclare explicitement.
            return $this->payrollCalculator->rulesResolver()->resolve($countryCode);
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
