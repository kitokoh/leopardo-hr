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

    /**
     * SMIG secteur non agricole 2026 — régime 48 h/semaine : 554,736 TND/mois
     * (décret n° 2026-67, revalorisation en vigueur au 01/01/2026, publiée
     * avril 2026 ; 470,251 TND en régime 40 h). Référence :
     * docs/payroll/TN_COMPLIANCE.md §3.
     */
    public function minimumWage(): float
    {
        return 554.736;
    }

    /**
     * Audit légal 2026 (issue #5249) — sources : CNSS.tn (régime non
     * agricole), CLEISS (fiche Tunisie), SmartPaie 2026, LF 2025 art. 17
     * (loi n° 2024-48 du 09/12/2024).
     *
     *   CNSS régime non agricole : 9,18 % sal. / 16,57 % pat. — SANS plafond
     *   général (le seuil « 6 × SMIG » concerne le régime complémentaire).
     *   Fonds perte d'emploi (LF 2025) : 0,50 % sal. / 0,50 % pat.
     *   ASSP accidents du travail : 0,4 % à 4 % patronal selon le secteur —
     *   valeur pilote retenue 1,00 % (commerce/services), surchargeable en
     *   base via social_contributions.
     */
    public function socialContributions(): array
    {
        return [
            ['name' => 'CNSS Salariale (régime non agricole)', 'code' => 'CNSS_TN_EMP', 'type' => 'employee', 'rate' => 9.18, 'cap' => null],
            ['name' => 'CNSS Patronale (régime non agricole)', 'code' => 'CNSS_TN_PAT', 'type' => 'employer', 'rate' => 16.57, 'cap' => null],
            ['name' => 'Fonds perte d\'emploi salarié', 'code' => 'PLE_TN_EMP', 'type' => 'employee', 'rate' => 0.50, 'cap' => null],
            ['name' => 'Fonds perte d\'emploi patronal', 'code' => 'PLE_TN_PAT', 'type' => 'employer', 'rate' => 0.50, 'cap' => null],
            ['name' => 'ASSP — accidents du travail et maladies professionnelles (patronale)', 'code' => 'ASSP_TN_PAT', 'type' => 'employer', 'rate' => 1.00, 'cap' => null],
        ];
    }

    /**
     * Barème IRPP 2026 (CGI TN — art. 36 de la loi n° 2024-48 du 09/12/2024,
     * LF 2025, en vigueur depuis le 01/01/2025) : 8 tranches ANNUELES
     * progressives sur le revenu net imposable (brut − cotisations
     * salariales − abattement frais professionnels art. 39) :
     *   0–5 000 0 % · 5 001–10 000 15 % · 10 001–20 000 25 % ·
     *   20 001–30 000 30 % · 30 001–40 000 33 % · 40 001–50 000 36 % ·
     *   50 001–70 000 38 % · > 70 000 40 %.
     */
    protected function defaultTaxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 5000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 5001, 'max' => 10000, 'rate' => 15, 'fixed_deduction' => 0],
            ['min' => 10001, 'max' => 20000, 'rate' => 25, 'fixed_deduction' => 0],
            ['min' => 20001, 'max' => 30000, 'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 30001, 'max' => 40000, 'rate' => 33, 'fixed_deduction' => 0],
            ['min' => 40001, 'max' => 50000, 'rate' => 36, 'fixed_deduction' => 0],
            ['min' => 50001, 'max' => 70000, 'rate' => 38, 'fixed_deduction' => 0],
            ['min' => 70001, 'max' => null, 'rate' => 40, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        // CGI TN art. 39 : abattement de 10 % sur le revenu imposable ANNUEL
        // (plancher 1 000 / plafond 1 500 TND/an) appliqué AVANT le barème
        // progressif (méthode dédiée applyAnnualAbatement() — constitution
        // §III). Barème LF 2025 art. 36 (8 tranches, max 40 %).
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
        // ZONE-INFRA (#1820) : chaque cotisation via computeContribution()
        // (constitution §III). Salarié = CNSS 9,18 % + perte d'emploi 0,50 % ;
        // employeur = CNSS 16,57 % + perte d'emploi 0,50 % + ASSP (pilot 1,00 %).
        $employee = round(
            $this->computeContribution($grossSalary, 'CNSS_TN_EMP', 9.18, null)
            + $this->computeContribution($grossSalary, 'PLE_TN_EMP', 0.50, null),
            2,
        );

        $employer = round(
            $this->computeContribution($grossSalary, 'CNSS_TN_PAT', 16.57, null)
            + $this->computeContribution($grossSalary, 'PLE_TN_PAT', 0.50, null)
            + $this->computeContribution($grossSalary, 'ASSP_TN_PAT', 1.00, null),
            2,
        );

        return [
            'employee' => $employee,
            'employer' => $employer,
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
