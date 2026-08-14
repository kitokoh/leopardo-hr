<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;

/**
 * MULTI-PAYS (#1869) — contrat de calcul complet et explicable.
 *
 * Présenteur UNIQUE du résultat de calcul paie (simulation ET bulletin
 * utilisent les mêmes appels métier — calculateSocialCharges() +
 * calculateIncomeTax() — afin qu'un même brut + même contexte produise
 * les mêmes montants partout) :
 *
 *   gross · social_employee · tax_base · income_tax · other_deductions ·
 *   net_salary · social_employer · total_cost
 *   + country_code · currency · rules_period · slab_version
 *
 * Politique d'arrondi (uniforme) : `round($x, 2)` — arrondi PHP standard
 * (half away from zero), appliqué à CHAQUE étape (cotisations, assiette,
 * impôt, net, coût employeur) et jamais de double arrondi sur le net
 * (net = brut − retenues, calculé une seule fois sur les valeurs déjà
 * arrondies).
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
     *   gross: float,
     *   social_employee: float,
     *   tax_base: float,
     *   income_tax: float,
     *   other_deductions: float,
     *   net_salary: float,
     *   social_employer: float,
     *   total_cost: float,
     *   rules_period: string,
     *   slab_version: string
     * }
     */
    public function present(string $countryCode, float $gross, ?string $companyId = null, ?\DateTimeInterface $asOf = null): array
    {
        $rules = $this->resolver->resolve($countryCode, $companyId, $asOf);

        /** @var array{employee: float, employer: float} $social */
        $social = $rules->calculateSocialCharges($gross);

        $grossRounded = round($gross, 2);
        $socialEmployee = round($social['employee'], 2);
        $socialEmployer = round($social['employer'], 2);
        $taxBase = round($grossRounded - $socialEmployee, 2);
        $incomeTax = $rules->calculateIncomeTax($taxBase);
        // Net = brut − retenues applicables, SANS double comptage.
        $netSalary = round($grossRounded - $socialEmployee - $incomeTax, 2);

        return [
            'country_code' => $rules->countryCode(),
            'currency' => $rules->currency(),
            'gross' => $grossRounded,
            'social_employee' => $socialEmployee,
            'tax_base' => $taxBase,
            'income_tax' => $incomeTax,
            'other_deductions' => 0.0,
            'net_salary' => $netSalary,
            'social_employer' => $socialEmployer,
            'total_cost' => round($grossRounded + $socialEmployer, 2),
            'rules_period' => ($asOf ?? now())->format('Y-m-d'),
            'slab_version' => $this->slabVersion($rules),
        ];
    }

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
