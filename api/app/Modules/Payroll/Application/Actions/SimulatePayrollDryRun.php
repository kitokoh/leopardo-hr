<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Exceptions\PayrollPlaceholderAcknowledgementRequiredException;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AbstractCountryRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculationAuditRecorder;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use ReflectionClass;

/**
 * Cas d'usage : simulation d'impact d'un bareme fiscal (dry-run, #1814) -
 * execute le moteur de paie reel sur un brut et un pays donnes, avec un
 * bareme fourni en parametre (`slabs_override`) ou le bareme actuel, et
 * option `ignore_caps` pour comparer  avec/sans plafond legal  (#1815).
 *
 * Orchestration pure et nommable (ADR-0020, lot 6 - #6968) : resolution des
 * regles pays (avec audit des echecs), garde  placeholder  (#1872/#5623 -
 * l'Action lève `PayrollPlaceholderAcknowledgementRequiredException`,
 * l'interface la rend en 422), overrides dry-run non persistants, calcul par
 * le pipeline unique des bulletins (`computeNetBreakdown`, #2220/#1869) et
 * audit de la simulation (resultats agreges uniquement).
 *
 * Ne persiste RIEN. L'interface (contrôleur) conserve l'autorisation
 * (manager principal/comptable ou platform_admin), la validation, l'audit
 * HTTP de l'acceptation placeholder et l'enveloppe de reponse.
 *
 * @param  array<int, array{min: float|string, max?: float|string|null, rate: float|string, fixed_deduction?: float|string}>|null  $slabsOverride
 * @return array{
 *     gross: float,
 *     country_code: string,
 *     rules_meta: array{short_name: string, confidence: string},
 *     compliance: array{level: string, warning: string, warning_key: string, source: string, verification_date: string|null},
 *     social_employee: float,
 *     social_employer: float,
 *     tax_base: float,
 *     income_tax: float,
 *     income_tax_by_slab: array<int, array{min: float, max: float|null, rate: float, amount: float, base: float}>,
 *     net: float,
 *     total_cost: float,
 * }
 */
class SimulatePayrollDryRun
{
    public function __construct(
        private readonly PayrollCalculator $payrollCalculator,
        private readonly PayrollCalculationAuditRecorder $auditRecorder,
    ) {}

    /**
     * @param  array<int|string, array{min: float|string, max?: float|string|null, rate: float|string, fixed_deduction?: float|string}>|null  $slabsOverride
     * @return array<string, mixed>
     */
    public function execute(
        ?string $companyId,
        string $countryCode,
        float $gross,
        ?array $slabsOverride,
        bool $ignoreCaps,
        bool $acknowledgePlaceholder,
        string $correlationId,
    ): array {
        $hasSlabsOverride = $slabsOverride !== null;

        // Issue #1874 - la resolution echoue → audit (rule_missing /
        // provider_error) puis relance ; la reponse HTTP reste inchangee.
        $rules = $this->resolveRules($correlationId, $companyId, $countryCode, $gross, $hasSlabsOverride);

        // Issue #1872/#5623 - regle  placeholder  (aucune valeur legale
        // implementee) : simulation indicative interdite sans confirmation
        // explicite ; l'acceptation est AUDITÉE côté interface.
        if ($rules->confidenceLevel() === 'placeholder' && ! $acknowledgePlaceholder) {
            throw new PayrollPlaceholderAcknowledgementRequiredException($countryCode);
        }

        // Override dry-run du bareme (non persistant).
        if ($hasSlabsOverride) {
            /** @var array<int, array{min: float, max: float|null, rate: float, fixed_deduction: float}> $slabs */
            $slabs = array_values(array_map(static fn (array $slab): array => [
                'min' => (float) $slab['min'],
                'max' => ($slab['max'] ?? null) !== null ? (float) $slab['max'] : null,
                'rate' => (float) $slab['rate'],
                'fixed_deduction' => (float) ($slab['fixed_deduction'] ?? 0),
            ], $slabsOverride));

            $rules->withTaxSlabs($slabs);
        }

        // Issue #1815 - comparaison  avec/sans plafond legal  : la methode
        // vit sur AbstractCountryRules (pas sur le contrat) - garde instanceof.
        if ($rules instanceof AbstractCountryRules) {
            $rules->withCapsEnabled(! $ignoreCaps);
        }

        // Issue #2220 - parite simulation/bulletin : pipeline UNIQUE des
        // bulletins (computeNetBreakdown), TRIMF SN comprise (#1869).
        $breakdown = $this->payrollCalculator->computeNetBreakdown($gross, $rules);
        $social = $breakdown['social'];
        $taxBase = round($breakdown['taxable_gross'], 2);
        $incomeTax = $breakdown['income_tax'];
        $netSalary = $breakdown['net_salary'];
        $totalCost = $breakdown['total_cost'];

        // Impot par tranche : convention mensuelle OU annualisee selon la
        // regle pays - le total converge vers l'impôt du moteur (#2220).
        $bySlab = $this->payrollCalculator->slabTaxBreakdown($rules, $gross, $taxBase, $incomeTax);

        // Issue #1874 — audit de la simulation (résultats agrégés uniquement).
        $this->auditRecorder->recordSimulation(
            $correlationId,
            $companyId,
            $countryCode,
            ['gross_salary' => $gross, 'has_slabs_override' => $hasSlabsOverride],
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
            (new ReflectionClass($rules))->getShortName(),
        );

        return [
            'gross' => $gross,
            'country_code' => $countryCode,
            // Consomme par l'interface pour l'audit HTTP de l'acceptation
            // placeholder (jamais expose dans la reponse).
            'rules_meta' => [
                'short_name' => (new ReflectionClass($rules))->getShortName(),
                'confidence' => $rules->confidenceLevel(),
            ],
            // Issue #1872 - conformite : niveau de confiance + avertissement
            // localise + source legale + date de verification experte (meme
            // structure que le contrat du PayrollCalculationPresenter).
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
        ];
    }

    /**
     * Resout les regles pays pour la simulation ; toute erreur de resolution
     * est tracee dans l'audit (rule_missing / provider_error) puis relancée —
     * la réponse HTTP reste inchangée.
     */
    private function resolveRules(
        string $correlationId,
        ?string $companyId,
        string $countryCode,
        float $gross,
        bool $hasSlabsOverride,
    ): CountryRulesInterface {
        $input = ['gross_salary' => $gross, 'has_slabs_override' => $hasSlabsOverride];

        try {
            // Appel direct au resolveur (comme le simulateur cotisations) :
            // `getRules()` masque l'exception de contexte pour PHPStan (dead
            // catch catch.neverThrown) alors que `resolve()` la déclare.
            return $this->payrollCalculator->rulesResolver()->resolve($countryCode);
        } catch (UnsupportedCountryRulesException $exception) {
            $this->auditRecorder->recordSimulation(
                $correlationId,
                $companyId,
                $countryCode,
                $input,
                null,
                PayrollCalculationAudit::STATUS_RULE_MISSING,
            );

            throw $exception;
        } catch (\Throwable $exception) {
            $this->auditRecorder->recordSimulation(
                $correlationId,
                $companyId,
                $countryCode,
                $input,
                null,
                PayrollCalculationAudit::STATUS_PROVIDER_ERROR,
            );

            throw $exception;
        }
    }
}
