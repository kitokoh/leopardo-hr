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
        // #1827 : composantes légales complètes — IPRES régime général T1
        // (plafond 432 000 XOF/mois), IPRES régime cadres T2 (tranche
        // 432 001–2 160 000), CSS famille 3 % (sans plafond), CSS AT 1 %
        // (pilote), CFCE 3 % (charge patronale).
        return [
            ['name' => 'IPRES Salariale T1', 'code' => 'IPRES_SN_EMP', 'type' => 'employee', 'rate' => 5.6, 'cap' => 432000.0],
            ['name' => 'IPRES Patronale T1', 'code' => 'IPRES_SN_PAT', 'type' => 'employer', 'rate' => 8.4, 'cap' => 432000.0],
            ['name' => 'IPRES Salariale T2 cadres', 'code' => 'IPRES_SN_EMP_T2', 'type' => 'employee', 'rate' => 2.4, 'cap' => null],
            ['name' => 'IPRES Patronale T2 cadres', 'code' => 'IPRES_SN_PAT_T2', 'type' => 'employer', 'rate' => 3.6, 'cap' => null],
            ['name' => 'CSS Famille Patronale', 'code' => 'CSS_SN_PAT', 'type' => 'employer', 'rate' => 3.0, 'cap' => null],
            ['name' => 'CSS AT Patronale', 'code' => 'CSS_SN_PAT_AT', 'type' => 'employer', 'rate' => 1.0, 'cap' => null],
            ['name' => 'CFCE', 'code' => 'CFCE_SN_PAT', 'type' => 'employer', 'rate' => 3.0, 'cap' => null],
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

        // Régime cadres T2 : tranche 432 001 – 2 160 000 XOF/mois.
        // ⚠️ Approximation pilot : appliqué à tous les salariés (aucun flag
        // « cadre » dans le moteur à ce jour) — voir SN_COMPLIANCE.md §3.
        $t2Base = max(0.0, min($grossSalary, 2160000.0) - $ipresCap);

        return [
            'employee' => round(
                $this->computeContribution($grossSalary, 'IPRES_SN_EMP', 5.6, $ipresCap)
                + $t2Base * 2.4 / 100,
                2,
            ),
            'employer' => round(
                $this->computeContribution($grossSalary, 'IPRES_SN_PAT', 8.4, $ipresCap)
                + $this->computeContribution($grossSalary, 'CSS_SN_PAT', 3.0, null)
                + $this->computeContribution($grossSalary, 'CSS_SN_PAT_AT', 1.0, null)
                + $this->computeContribution($grossSalary, 'CFCE_SN_PAT', 3.0, null)
                + $t2Base * 3.6 / 100,
                2,
            ),
        ];
    }

    public function calculateBracketTax(float $grossSalary): float
    {
        // #1827 : TRIMF (Taxe Représentative des Impôts du Minimum Fiscal) —
        // taxe forfaitaire mensuelle retenue sur le salarié (CGI Sénégal).
        // [plafond de tranche, montant forfaitaire]
        $trimf = [
            [25000.0, 900.0],
            [75000.0, 2700.0],
            [150000.0, 5400.0],
            [350000.0, 9000.0],
            [700000.0, 18000.0],
            [PHP_FLOAT_MAX, 36000.0],
        ];

        foreach ($trimf as [$ceiling, $amount]) {
            if ($grossSalary <= $ceiling) {
                return $amount;
            }
        }

        return 36000.0;
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

    /**
     * #1827 : abattement frais professionnels 30 % du brut, non plafonné
     * (CGI Sénégal).
     *
     * @return array{rate: float, cap: float|null}
     */
    public function professionalExpensesDeduction(): array
    {
        return ['rate' => 30.0, 'cap' => null];
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * #1827 : préavis légal Sénégal (Code du travail art. L.45) — 8 jours
     * ouvriers, 1 mois employés/techniciens, 3 mois cadres. Sans catégorie
     * dans le moteur, l'ancienneté module : < 1 an → 8 j ; < 5 ans → 1 mois ;
     * ≥ 5 ans → 3 mois (approximation pilot, SN_COMPLIANCE.md §5).
     */
    public function noticePeriodDays(float $yearsOfService): float
    {
        if ($yearsOfService < 1.0) {
            return 8.0;
        }
        if ($yearsOfService < 5.0) {
            return 30.0;
        }

        return 90.0;
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
