<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

class TunisiaPayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'TN';
    }

    public function currency(): string
    {
        return 'TND';
    }

    public function minimumWage(): float
    {
        return 480.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'CNSS Salariale', 'code' => 'CNSS_TN_EMP', 'type' => 'employee', 'rate' => 9.18, 'cap' => null],
            ['name' => 'CNSS Patronale', 'code' => 'CNSS_TN_PAT', 'type' => 'employer', 'rate' => 16.57, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 5000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 5001, 'max' => 20000, 'rate' => 26, 'fixed_deduction' => 0],
            ['min' => 20001, 'max' => 30000, 'rate' => 28, 'fixed_deduction' => 0],
            ['min' => 30001, 'max' => 50000, 'rate' => 32, 'fixed_deduction' => 0],
            ['min' => 50001, 'max' => null, 'rate' => 35, 'fixed_deduction' => 0],
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
            'employee' => round($grossSalary * 0.0918, 2),
            'employer' => round($grossSalary * 0.1657, 2),
        ];
    }

    public function timezone(): string
    {
        return 'Africa/Tunis';
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Sunday is the standard weekly rest day in Tunisia.
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
        return 'Placeholder: no official Tunisian public-holiday calendar is wired in yet; do not assume dates are complete or correct. Pending PA2-COUNTRY-012.';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-005: Tunisian labor code (Code du travail art. 79) sets
     * the legal weekly working-hours threshold at 48 hours/week for most
     * non-agricultural sectors (40h for some regulated sectors).
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 48.0;
    }

    /**
     * PA2-COUNTRY-005: Code du travail art. 90 majore les heures
     * supplementaires de 25% (au-dela de la duree normale hebdomadaire).
     * Modelise ici comme un palier unique, a titre pilote
     * (confidenceLevel='pilot').
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => null, 'multiplier' => 1.25],
        ];
    }
}
