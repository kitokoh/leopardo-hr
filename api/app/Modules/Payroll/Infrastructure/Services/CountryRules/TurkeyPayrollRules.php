<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

class TurkeyPayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'TR';
    }

    public function currency(): string
    {
        return 'TRY';
    }

    public function minimumWage(): float
    {
        return 20002.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'SGK Salariale', 'code' => 'SGK_TR_EMP', 'type' => 'employee', 'rate' => 14.0, 'cap' => null],
            ['name' => 'SGK Patronale', 'code' => 'SGK_TR_PAT', 'type' => 'employer', 'rate' => 20.5, 'cap' => null],
            ['name' => 'Chomage Salariale', 'code' => 'UNEMP_TR_EMP', 'type' => 'employee', 'rate' => 1.0, 'cap' => null],
            ['name' => 'Chomage Patronale', 'code' => 'UNEMP_TR_PAT', 'type' => 'employer', 'rate' => 2.0, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 110000, 'rate' => 15, 'fixed_deduction' => 0],
            ['min' => 110001, 'max' => 230000, 'rate' => 20, 'fixed_deduction' => 0],
            ['min' => 230001, 'max' => 580000, 'rate' => 27, 'fixed_deduction' => 0],
            ['min' => 580001, 'max' => 3000000, 'rate' => 35, 'fixed_deduction' => 0],
            ['min' => 3000001, 'max' => null, 'rate' => 40, 'fixed_deduction' => 0],
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
        return [
            'employee' => round($grossSalary * 0.15, 2),
            'employer' => round($grossSalary * 0.225, 2),
        ];
    }

    public function timezone(): string
    {
        return 'Europe/Istanbul';
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Sunday is the standard weekly rest day in Turkey.
        return [7];
    }

    /**
     * Monthly-only for now: not yet validated for daily/weekly pay cycles.
     *
     * @return array<int, string>
     */
    public function supportedPayCycles(): array
    {
        return ['monthly'];
    }

    public function publicHolidaysSource(): string
    {
        return 'Placeholder: no official Turkish public-holiday calendar is wired in yet; do not assume dates are complete or correct. Pending PA2-COUNTRY-012.';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['TR'].
     */
    public function language(): string
    {
        return 'tr';
    }

    /**
     * PA2-COUNTRY-006: explicit compliance disclaimer required by the
     * ticket acceptance criteria ("seuils prudents et avertissement
     * conformite"). Overrides AbstractCountryRules::complianceWarning()
     * with wording specific to Turkish payroll law.
     */
    public function complianceWarning(): string
    {
        return 'Pilot ruleset for Turkiye: income tax slabs, SGK/unemployment '.
            'contribution rates and the 45h/week overtime tier are sourced from '.
            'general public references (Labor Law No. 4857) and are NOT a '.
            'substitute for a certified Turkish payroll provider or local mali '.
            'musavir. Do not rely on this for statutory payslip compliance '.
            'without validation.';
    }

    /**
     * PA2-COUNTRY-005 baseline: Turkish Labor Law No. 4857 art. 63 sets the
     * legal weekly working-hours threshold at 45 hours/week.
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 45.0;
    }

    /**
     * PA2-COUNTRY-005 baseline: Labor Law No. 4857 art. 41 majore les heures
     * supplementaires de 50% du salaire horaire normal. Modelise ici comme
     * un palier unique, a titre pilote (confidenceLevel='pilot').
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
