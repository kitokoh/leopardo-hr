<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;

/**
 * MULTI-PAYS (#1869) — contrat de calcul complet et explicable.
 *
 * Présenteur UNIQUE du résultat de calcul paie. Depuis #1869, les calculs
 * sont délégués à `PayrollCalculator::computeNetBreakdown()` — le noyau
 * partagé par la simulation ET `PayrollCalculator::calculateSlip()` : un
 * même brut + même contexte produit STRICTEMENT les mêmes montants partout
 * (cotisations, assiette, impôt, taxe de minimum fiscal, net, coût
 * employeur).
 *
 * Contrat exposé :
 *   gross · social_employee · tax_base · income_tax · bracket_tax ·
 *   other_deductions · net_salary · social_employer · total_cost
 *   + country_code · currency · rules_identifier · rules_period ·
 *     slab_version · confidence_level · rounding_policy
 *
 * Politique d'arrondi (uniforme, docs/payroll/CALCULATION_CONTRACT.md) :
 * l'impôt est calculé sur l'assiette NON arrondie (brut − cotisations,
 * comme le bulletin) ; seuls les montants exposés sont arrondis à 2
 * décimales (demi au plus proche) ; le net a un plancher à 0.
 */
class PayrollCalculationPresenter
{
    public function __construct(private readonly CountryRulesResolver $resolver) {}

    /**
     * Calcule et présente le contrat complet pour un brut donné.
     *
     * @return array{
     *   country_code: string,
     *   currency: string,
     *   rules_identifier: string,
     *   rules_period: string,
     *   slab_version: string,
     *   rules_version: string,
     *   confidence_level: string,
     *   compliance: array{level: string, warning: string, warning_key: string, source: string, verification_date: string|null},
     *   rounding_policy: string,
     *   gross: float,
     *   social_employee: float,
     *   tax_base: float,
     *   income_tax: float,
     *   bracket_tax: float,
     *   other_deductions: float,
     *   net_salary: float,
     *   social_employer: float,
     *   total_cost: float
     * }
     */
    public function present(string $countryCode, float $gross, ?string $companyId = null, ?\DateTimeInterface $asOf = null): array
    {
        $rules = $this->resolver->resolve($countryCode, $companyId, $asOf);

        // Issue #1869 — mêmes appels métier que calculateSlip() : le noyau de
        // calcul commun (social, impôt sur assiette non arrondie avec le brut
        // réel pour l'abattement frais pro, taxe de minimum fiscal, net, coût).
        $breakdown = (new PayrollCalculator)->computeNetBreakdown($gross, $rules);

        return [
            'country_code' => $rules->countryCode(),
            'currency' => $rules->currency(),
            'rules_identifier' => (new \ReflectionClass($rules))->getShortName(),
            'rules_period' => ($asOf ?? now())->format('Y-m-d'),
            'slab_version' => $this->slabVersion($rules),
            'rules_version' => $rules->rulesVersion(),
            'confidence_level' => $rules->confidenceLevel(),
            // Issue #1872 — avertissement de conformité structuré (niveau,
            // message localisé, source légale, date de vérification experte).
            'compliance' => [
                'level' => $rules->confidenceLevel(),
                'warning' => $rules->complianceWarning(),
                'warning_key' => 'payroll.compliance_warning_'.$rules->confidenceLevel(),
                'source' => $rules->complianceSource(),
                'verification_date' => $rules->verificationDate(),
            ],
            'rounding_policy' => self::ROUNDING_POLICY,
            'gross' => round($gross, 2),
            'social_employee' => $breakdown['social']['employee'],
            'tax_base' => round($breakdown['taxable_gross'], 2),
            'income_tax' => $breakdown['income_tax'],
            'bracket_tax' => $breakdown['bracket_tax'],
            // Rétro-compatible : `other_deductions` = taxe de minimum fiscal
            // (TRIMF/CN…) — la seule déduction forfaitaire hors impôt/cotisations.
            'other_deductions' => $breakdown['bracket_tax'],
            'net_salary' => $breakdown['net_salary'],
            'social_employer' => $breakdown['social']['employer'],
            'total_cost' => $breakdown['total_cost'],
        ];
    }

    /**
     * Politique d'arrondi du moteur de paie — documentée dans
     * docs/payroll/CALCULATION_CONTRACT.md (issue #1869).
     */
    private const ROUNDING_POLICY = 'Montants arrondis à 2 décimales (demi au plus proche, PHP round). L\'impôt est calculé sur l\'assiette non arrondie (brut − cotisations salariales), comme sur le bulletin ; seuls les champs exposés sont arrondis. net_salary = max(0, brut − total_deductions).';

    /**
     * Empreinte stable des barèmes (tranches fiscales + cotisations) :
     * permet d'identifier la version des règles appliquées.
     */
    private function slabVersion(CountryRulesInterface $rules): string
    {
        $payload = json_encode([
            'taxSlabs' => $rules->taxSlabs(),
            'socialContributions' => $rules->socialContributions(),
        ], JSON_THROW_ON_ERROR);

        return substr(hash('sha256', $payload), 0, 12);
    }
}
