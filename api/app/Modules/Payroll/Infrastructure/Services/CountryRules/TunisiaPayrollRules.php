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

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        // Issue #2261 — CGI TN art. 39 : abattement de 10 % sur le revenu
        // imposable ANNUEL (plancher 1 000 / plafond 1 500 TND/an) appliqué
        // AVANT le barème progressif. Méthode dédiée (constitution §III) :
        // la valeur légale vit dans une méthode, pas inline.
        $annualTaxable = $this->applyAnnualAbatement($grossTaxable * $annualBasis);
        $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

        return round($tax / $annualBasis, 2);
    }

    /**
     * Issue #2261 — abattement IRPP tunisien (CGI TN art. 39) : 10 % du
     * revenu annuel imposable, borné [1 000 ; 1 500 TND/an].
     *
     * @see docs/payroll/TN_COMPLIANCE.md §1
     */
    public function applyAnnualAbatement(float $annualTaxable): float
    {
        $abatement = min(max($annualTaxable * 0.10, 1000.0), 1500.0);

        return max(0.0, $annualTaxable - $abatement);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        $employeeRate = $this->resolveContributionRate('CNSS_TN_EMP', 9.18);
        $employerRate = $this->resolveContributionRate('CNSS_TN_PAT', 16.57);

        return [
            'employee' => round($grossSalary * $employeeRate / 100, 2),
            'employer' => round($grossSalary * $employerRate / 100, 2),
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
        return 'TN fixed public holidays (décrets, seed PublicHolidaySeeder, issue #2255): 1er jan, 14 jan, 20 mars, 9 avr, 1er mai, 25 juil, 13 août, 15 oct, 17 déc + mobiles islamiques (Aïd el-Fitr, Aïd el-Adha, Aïd el-Mawlid) — PA2-COUNTRY-012.';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['TN'].
     */
    public function language(): string
    {
        return 'fr';
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
