<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

class AlgeriaPayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'DZ';
    }

    public function currency(): string
    {
        return 'DZD';
    }

    public function minimumWage(): float
    {
        return 20000.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'CNAS Salariale', 'code' => 'CNAS_EMP', 'type' => 'employee', 'rate' => 9.0, 'cap' => null],
            ['name' => 'CNAS Patronale', 'code' => 'CNAS_PAT', 'type' => 'employer', 'rate' => 26.0, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 20000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 20001, 'max' => 40000, 'rate' => 23, 'fixed_deduction' => 0],
            ['min' => 40001, 'max' => 80000, 'rate' => 27, 'fixed_deduction' => 0],
            ['min' => 80001, 'max' => 160000, 'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 160001, 'max' => 320000, 'rate' => 33, 'fixed_deduction' => 0],
            ['min' => 320001, 'max' => null, 'rate' => 35, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12): float
    {
        $tax = $this->calculateProgressiveTax($grossTaxable, $this->taxSlabs());

        $annualTax = $tax * $annualBasis;
        $abatement = min(max($annualTax * 0.40, 12000), 18000);
        $finalAnnualTax = max(0, $annualTax - $abatement);

        return round($finalAnnualTax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        $employeeRate = $this->resolveContributionRate('CNAS_EMP', 9.0);
        $employerRate = $this->resolveContributionRate('CNAS_PAT', 26.0);

        return [
            'employee' => round($grossSalary * $employeeRate / 100, 2),
            'employer' => round($grossSalary * $employerRate / 100, 2),
        ];
    }

    public function timezone(): string
    {
        return 'Africa/Algiers';
    }

    /**
     * PA2-COUNTRY-004: Algerian labor code (loi 90-11 art. 27 modifiee) sets a
     * weekend of Friday + Saturday as the standard legal weekly rest for most
     * sectors (moved from the historical Thursday/Friday weekend by decret
     * 2009 for public administration, since generalized in practice).
     *
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        return [5, 6];
    }

    /**
     * @return array<int, string>
     */
    public function supportedPayCycles(): array
    {
        return ['daily', 'weekly', 'monthly'];
    }

    public function publicHolidaysSource(): string
    {
        return 'placeholder: no official Algerian public-holiday calendar is wired in yet; do not assume dates are complete or correct. Pending PA2-COUNTRY-012.';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['DZ'].
     */
    public function language(): string
    {
        return 'fr';
    }

    /**
     * PA2-COUNTRY-004: standard Algerian legal weekly working-hours threshold
     * (loi 90-11 art. 26 : duree legale hebdomadaire de 40 heures pour un
     * horaire normal ; au-dela = heures supplementaires).
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 40.0;
    }

    /**
     * PA2-COUNTRY-004: loi 90-11 art. 33 majore les heures supplementaires
     * d'au moins 50% du salaire horaire normal, sans distinction de palier
     * dans le texte general (contrairement a la France). Modelise ici comme
     * un palier unique et illimite a titre pilote (confidenceLevel='pilot'),
     * a valider legalement avant usage paie reel.
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => null, 'multiplier' => 1.5],
        ];
    }
}
