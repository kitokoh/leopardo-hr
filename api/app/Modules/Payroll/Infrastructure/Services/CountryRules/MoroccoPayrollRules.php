<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

class MoroccoPayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'MA';
    }

    public function currency(): string
    {
        return 'MAD';
    }

    public function minimumWage(): float
    {
        return 3111.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'CNSS Salariale', 'code' => 'CNSS_EMP', 'type' => 'employee', 'rate' => 4.48, 'cap' => 6000],
            ['name' => 'CNSS Patronale', 'code' => 'CNSS_PAT', 'type' => 'employer', 'rate' => 8.98, 'cap' => 6000],
            ['name' => 'AMO Salariale', 'code' => 'AMO_EMP', 'type' => 'employee', 'rate' => 2.26, 'cap' => null],
            ['name' => 'AMO Patronale', 'code' => 'AMO_PAT', 'type' => 'employer', 'rate' => 4.11, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 30000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 30001, 'max' => 50000, 'rate' => 10, 'fixed_deduction' => 3000],
            ['min' => 50001, 'max' => 60000, 'rate' => 20, 'fixed_deduction' => 8000],
            ['min' => 60001, 'max' => 80000, 'rate' => 30, 'fixed_deduction' => 14000],
            ['min' => 80001, 'max' => 180000, 'rate' => 34, 'fixed_deduction' => 17200],
            ['min' => 180001, 'max' => null, 'rate' => 38, 'fixed_deduction' => 24400],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12): float
    {
        $annualTaxable = $grossTaxable * $annualBasis;
        $tax = 0.0;
        $fixedDeduction = 0.0;

        foreach ($this->taxSlabs() as $slab) {
            $max = $slab['max'] ?? PHP_FLOAT_MAX;
            if ($annualTaxable >= $slab['min'] && $annualTaxable <= $max) {
                $tax = $annualTaxable * ($slab['rate'] / 100);
                $fixedDeduction = $slab['fixed_deduction'];
                break;
            }
        }

        return round(max(0, ($tax - $fixedDeduction)) / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        $cnssBase = min($grossSalary, 6000);

        return [
            'employee' => round($cnssBase * 0.0448 + $grossSalary * 0.0226, 2),
            'employer' => round($cnssBase * 0.0898 + $grossSalary * 0.0411, 2),
        ];
    }

    public function timezone(): string
    {
        return 'Africa/Casablanca';
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Sunday is the standard weekly rest day in Morocco.
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
        return 'Placeholder: no official Moroccan public-holiday calendar is wired in yet; do not assume dates are complete or correct. Pending PA2-COUNTRY-012.';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['MA'].
     */
    public function language(): string
    {
        return 'fr';
    }

    /**
     * PA2-COUNTRY-005: Moroccan labor code (loi 65-99) sets the legal weekly
     * working-hours threshold at 44 hours/week for most non-agricultural
     * sectors.
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 44.0;
    }

    /**
     * PA2-COUNTRY-005: loi 65-99 art. 201 majore les heures supplementaires
     * de 25% (heures de jour) a 50% (heures de nuit/jour de repos), avec des
     * taux plus eleves les jours feries. Modelise ici uniquement le palier
     * par defaut "heures de jour", a titre pilote (confidenceLevel='pilot') ;
     * la distinction jour/nuit/ferie necessite un horodatage non disponible
     * dans cette interface generique.
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
