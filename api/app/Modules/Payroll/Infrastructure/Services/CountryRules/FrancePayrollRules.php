<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

class FrancePayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'FR';
    }

    public function currency(): string
    {
        return 'EUR';
    }

    public function minimumWage(): float
    {
        return 1766.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'Securite sociale salariale', 'code' => 'SS_FR_EMP', 'type' => 'employee', 'rate' => 7.5, 'cap' => null],
            ['name' => 'Securite sociale patronale', 'code' => 'SS_FR_PAT', 'type' => 'employer', 'rate' => 30.0, 'cap' => null],
            ['name' => 'CSG', 'code' => 'CSG_FR', 'type' => 'employee', 'rate' => 9.2, 'cap' => null],
            ['name' => 'CRDS', 'code' => 'CRDS_FR', 'type' => 'employee', 'rate' => 0.5, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 11294, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 11295, 'max' => 28797, 'rate' => 11, 'fixed_deduction' => 0],
            ['min' => 28798, 'max' => 82341, 'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 82342, 'max' => 177106, 'rate' => 41, 'fixed_deduction' => 0],
            ['min' => 177107, 'max' => null, 'rate' => 45, 'fixed_deduction' => 0],
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
        // 98.25% CSG/CRDS abatement base is a statutory constant, not a
        // per-code rate/cap tracked in social_contributions, so it stays
        // hardcoded here.
        $csgBase = $grossSalary * 0.9825;

        $ssEmployeeRate = $this->resolveContributionRate('SS_FR_EMP', 7.5);
        $ssEmployerRate = $this->resolveContributionRate('SS_FR_PAT', 30.0);
        $csgRate = $this->resolveContributionRate('CSG_FR', 9.2);
        $crdsRate = $this->resolveContributionRate('CRDS_FR', 0.5);

        return [
            'employee' => round($grossSalary * $ssEmployeeRate / 100 + $csgBase * $csgRate / 100 + $csgBase * $crdsRate / 100, 2),
            'employer' => round($grossSalary * $ssEmployerRate / 100, 2),
        ];
    }

    public function timezone(): string
    {
        return 'Europe/Paris';
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Saturday and Sunday are the standard weekly rest days in France.
        return [6, 7];
    }

    /**
     * Monthly-only for now: French payroll (bulletin de paie) is
     * structured around monthly cycles by convention; daily/weekly cycles
     * are not yet modeled for this country.
     *
     * @return array<int, string>
     */
    public function supportedPayCycles(): array
    {
        return ['monthly'];
    }

    public function publicHolidaysSource(): string
    {
        return 'Placeholder: no official French public-holiday calendar is wired in yet; do not assume dates are complete or correct. Pending PA2-COUNTRY-012.';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['FR'].
     */
    public function language(): string
    {
        return 'fr';
    }

    /**
     * PA2-COUNTRY-006: explicit compliance disclaimer required by the
     * ticket acceptance criteria ("seuils prudents et avertissement
     * conformite"). Overrides AbstractCountryRules::complianceWarning()
     * with wording specific to French payroll law, naming the concrete
     * areas (tax slabs, social contributions, overtime tiers) that are
     * pilot-sourced rather than validated against a payroll provider or
     * local counsel.
     */
    public function complianceWarning(): string
    {
        return 'Pilot ruleset for France: income tax slabs, social contribution '.
            'rates (securite sociale, CSG/CRDS) and the 35h/week overtime tiers '.
            'are sourced from general public references (Code du travail, '.
            'bareme fiscal indicatif) and are NOT a substitute for a certified '.
            'French payroll provider (DSN) or local expert-comptable. Do not '.
            'rely on this for statutory payslip compliance without validation.';
    }

    /**
     * PA2-COUNTRY-005 baseline: French Code du travail art. L3121-27 sets
     * the legal weekly working-hours threshold (duree legale) at 35
     * hours/week.
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 35.0;
    }

    /**
     * PA2-COUNTRY-005 baseline: Code du travail art. L3121-36 majore les 8
     * premieres heures supplementaires (36e a 43e heure) de 25%, puis les
     * heures suivantes de 50%, sauf accord de branche/entreprise different.
     * A titre pilote (confidenceLevel='pilot'), la convention collective
     * applicable peut modifier ces taux.
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => 8.0, 'multiplier' => 1.25],
            ['up_to_hours' => null, 'multiplier' => 1.5],
        ];
    }
}
