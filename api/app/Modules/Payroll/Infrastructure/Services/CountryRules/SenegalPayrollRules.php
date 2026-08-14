<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

class SenegalPayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'SN';
    }

    public function currency(): string
    {
        return 'XOF';
    }

    public function minimumWage(): float
    {
        return 58900.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'IPRES Salariale', 'code' => 'IPRES_SN_EMP', 'type' => 'employee', 'rate' => 5.6, 'cap' => null],
            ['name' => 'IPRES Patronale', 'code' => 'IPRES_SN_PAT', 'type' => 'employer', 'rate' => 8.4, 'cap' => null],
            ['name' => 'CSS Patronale', 'code' => 'CSS_SN_PAT', 'type' => 'employer', 'rate' => 3.0, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 630000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 630001, 'max' => 1500000, 'rate' => 20, 'fixed_deduction' => 0],
            ['min' => 1500001, 'max' => 4000000, 'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 4000001, 'max' => 8000000, 'rate' => 35, 'fixed_deduction' => 0],
            ['min' => 8000001, 'max' => 13500000, 'rate' => 37, 'fixed_deduction' => 0],
            ['min' => 13500001, 'max' => null, 'rate' => 40, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12): float
    {
        $annualTaxable = $grossTaxable * $annualBasis;
        $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

        return round($tax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        // ZONE-INFRA (#1820): IPRES/CSS are each capped at the statutory
        // ceiling (432 000 XOF/month) — the employer-side IPRES and CSS
        // contributions each get their own cap application instead of a
        // summed rate applied to the full gross (which overcharged above
        // the ceiling). The `social_contributions` DB rows may override
        // rates/caps (effective dating, company overrides).
        $ipresCap = 432000.0;

        return [
            'employee' => $this->computeContribution($grossSalary, 'IPRES_SN_EMP', 5.6, $ipresCap),
            'employer' => $this->computeContribution($grossSalary, 'IPRES_SN_PAT', 8.4, $ipresCap)
                + $this->computeContribution($grossSalary, 'CSS_SN_PAT', 3.0, $ipresCap),
        ];
    }

    public function timezone(): string
    {
        return 'Africa/Dakar';
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Sunday is the standard weekly rest day in Senegal.
        return [7];
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
        return 'placeholder: no official Senegalese public-holiday calendar is wired in yet; do not assume dates are complete or correct. Pending PA2-COUNTRY-012.';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['SN'].
     */
    public function language(): string
    {
        return 'fr';
    }

    /**
     * PA2-COUNTRY-005 baseline: Senegalese Code du travail sets the legal
     * weekly working-hours threshold at 40 hours/week for non-agricultural
     * sectors.
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 40.0;
    }

    /**
     * PA2-COUNTRY-005 baseline: Code du travail senegalais majore les
     * heures supplementaires (15% pour les 8 premieres heures/semaine,
     * jusqu'a 40% au-dela ou de nuit). Modelise ici un palier a 2 niveaux, a
     * titre pilote (confidenceLevel='pilot').
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => 8.0, 'multiplier' => 1.15],
            ['up_to_hours' => null, 'multiplier' => 1.40],
        ];
    }
}
