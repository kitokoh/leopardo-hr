<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

/**
 * United States (US) — règles fédérales 2026 (pilot).
 *
 * Audit légal 2026-08-24 (sources publiques) :
 *  - Federal income tax 2026 (single) : 7 tranches 10 % → 37 %
 *    (IRS Rev. Proc. 2025-32 — OBBBA : bornes 10/12 % sur-indexées) :
 *    10 % ≤ $12 400 · 12 % ≤ $50 400 · 22 % ≤ $105 700 · 24 % ≤ $201 775 ·
 *    32 % ≤ $256 225 · 35 % ≤ $640 600 · 37 % > $640 600.
 *  - Standard deduction 2026 (single) : $16 100 (OBBBA + indexation).
 *  - FICA : Social Security 6,2 % (sal./pat.) sur le wage base 2026
 *    $184 500 ; Medicare 1,45 % (sal./pat.) sans plafond ; Additional
 *    Medicare 0,9 % (salarié seul) au-delà de $200 000/an (single).
 *  - FUTA : 6,0 % nominal sur les premiers $7 000/an, crédit max 5,4 % →
 *    0,6 % effectif (employeur seul).
 *  - FLSA : seuil hebdo 40 h, majoration légale 1,5× (heure et demie).
 *  - Federal minimum wage : $7,25/h (inchangé depuis 2009) — les États
 *    peuvent imposer un minimum supérieur (non modélisé).
 *  - At-will employment : pas de préavis ni d'indemnité de licenciement
 *    statutaires (sauf WARN pour licenciements collectifs — hors moteur).
 *
 * Impôt d'État : non modélisé (pilot) — `stateWithholding` documenté dans
 * US_COMPLIANCE.md §6. Confidence : pilot.
 */
class UnitedStatesPayrollRules extends AbstractCountryRules
{
    /** Standard deduction fédérale 2026, statut single. */
    private const FEDERAL_STANDARD_DEDUCTION = 16100.0;

    /** Social Security wage base 2026 (IRS Topic 751). */
    private const SOCIAL_SECURITY_WAGE_BASE = 184500.0;

    /** Seuil Additional Medicare 2026 (single). */
    private const ADDITIONAL_MEDICARE_THRESHOLD = 200000.0;

    /** FUTA wage base annuel ($7 000, inchangé). */
    private const FUTA_WAGE_BASE = 7000.0;

    /** FUTA taux effectif après crédit maximal de 5,4 points. */
    private const FUTA_EFFECTIVE_RATE = 0.6;

    public function countryCode(): string
    {
        return 'US';
    }

    public function currency(): string
    {
        return 'USD';
    }

    public function minimumWage(): float
    {
        // Federal minimum wage $7,25/h × 173,33 h mensuelles ≈ $1 256,64.
        return 1256.64;
    }

    public function socialContributions(): array
    {
        return [
            // FICA — Social Security : 6,2 % chacun sur le wage base 2026
            // ($184 500/an → $15 375/mois).
            ['name' => 'FICA Social Security employee', 'code' => 'SS_US_EMP', 'type' => 'employee', 'rate' => 6.2, 'cap' => 15375.0],
            ['name' => 'FICA Social Security employer', 'code' => 'SS_US_PAT', 'type' => 'employer', 'rate' => 6.2, 'cap' => 15375.0],
            // FICA — Medicare : 1,45 % chacun, sans plafond.
            ['name' => 'FICA Medicare employee', 'code' => 'MED_US_EMP', 'type' => 'employee', 'rate' => 1.45, 'cap' => null],
            ['name' => 'FICA Medicare employer', 'code' => 'MED_US_PAT', 'type' => 'employer', 'rate' => 1.45, 'cap' => null],
            // Additional Medicare 0,9 % (salarié seul) au-delà de $200 000/an
            // (single) — le `floor` porte le seuil mensuel ($16 666,67).
            ['name' => 'Additional Medicare employee', 'code' => 'ADD_MED_US_EMP', 'type' => 'employee', 'rate' => 0.9, 'cap' => null, 'floor' => 16666.67],
            // FUTA 6,0 % nominal (plafond $7 000/an → $583,33/mois) ; le
            // calcul applique le taux effectif 0,6 % après crédit max 5,4 %.
            ['name' => 'FUTA employer (effective 0.6%)', 'code' => 'FUTA_US_PAT', 'type' => 'employer', 'rate' => 6.0, 'cap' => 583.33],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        // Tranches fédérales 2026 — statut single, sur le revenu imposable
        // (après standard deduction). Bornes inclusives du helper progressif.
        return [
            ['min' => 0, 'max' => 12400, 'rate' => 10, 'fixed_deduction' => 0],
            ['min' => 12401, 'max' => 50400, 'rate' => 12, 'fixed_deduction' => 0],
            ['min' => 50401, 'max' => 105700, 'rate' => 22, 'fixed_deduction' => 0],
            ['min' => 105701, 'max' => 201775, 'rate' => 24, 'fixed_deduction' => 0],
            ['min' => 201776, 'max' => 256225, 'rate' => 32, 'fixed_deduction' => 0],
            ['min' => 256226, 'max' => 640600, 'rate' => 35, 'fixed_deduction' => 0],
            ['min' => 640601, 'max' => null, 'rate' => 37, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        // Revenu imposable = revenu annuel − standard deduction (single).
        $annualTaxable = max(0.0, $grossTaxable * $annualBasis - self::FEDERAL_STANDARD_DEDUCTION);
        $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

        return round($tax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        $ssRate = $this->resolveContributionRate('SS_US_EMP', 6.2);
        $ssEmployerRate = $this->resolveContributionRate('SS_US_PAT', 6.2);
        $medicareRate = $this->resolveContributionRate('MED_US_EMP', 1.45);
        $medicareEmployerRate = $this->resolveContributionRate('MED_US_PAT', 1.45);
        $additionalRate = $this->resolveContributionRate('ADD_MED_US_EMP', 0.9);

        $ssMonthlyBase = self::SOCIAL_SECURITY_WAGE_BASE / 12; // $15 375,00
        $ssBase = $this->capsEnabled() ? min($grossSalary, $ssMonthlyBase) : $grossSalary;

        $ss = $ssBase * $ssRate / 100;
        $medicare = $grossSalary * $medicareRate / 100;

        // Additional Medicare 0,9 % : seuil ANNUEL $200 000 (single) —
        // appliqué sur la fraction mensuelle du dépassement annuel.
        $annualized = $grossSalary * 12;
        $additional = $annualized > self::ADDITIONAL_MEDICARE_THRESHOLD
            ? max(0.0, ($annualized - self::ADDITIONAL_MEDICARE_THRESHOLD) / 12) * $additionalRate / 100
            : 0.0;

        $employee = round($ss + $medicare + $additional, 2);

        // FUTA : taux effectif 0,6 % (crédit max 5,4 % sur le 6,0 % nominal),
        // plafonné aux premiers $7 000/an.
        $futaBase = $this->capsEnabled() ? min($grossSalary, self::FUTA_WAGE_BASE / 12) : $grossSalary;
        $futa = $futaBase * self::FUTA_EFFECTIVE_RATE / 100;

        $employer = round($ss + $grossSalary * $medicareEmployerRate / 100 + $futa, 2);

        return [
            'employee' => $employee,
            'employer' => $employer,
        ];
    }

    public function timezone(): string
    {
        // Fuseau par défaut (États continentaux) ; les entreprises peuvent
        // surcharger au niveau tenant.
        return 'America/New_York';
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        return [6, 7];
    }

    /**
     * @return array<int, string>
     */
    public function supportedPayCycles(): array
    {
        return ['monthly'];
    }

    public function publicHolidaysSource(): string
    {
        return 'US federal holidays (11 per year, fixed dates, OPM) — PA2-COUNTRY-012; state holidays are not modelled.';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['US'].
     */
    public function language(): string
    {
        return 'en';
    }

    public function complianceWarning(): string
    {
        return 'Pilot ruleset for the United States (federal only): income tax brackets and standard deduction (Rev. Proc. 2025-32), FICA (SS wage base $184,500) and FUTA are sourced from IRS public guidance but are NOT a substitute for a certified US payroll provider or local counsel. State income tax withholding is NOT modelled (see US_COMPLIANCE.md §6). Do not rely on this for statutory payroll compliance without validation.';
    }

    /**
     * FLSA : 40 h/semaine au-delà desquelles la majoration légale s'applique.
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 40.0;
    }

    /**
     * FLSA : heure et demie (1,5×) au-delà de 40 h/semaine, sans palier
     * supplémentaire fédéral.
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => null, 'multiplier' => 1.5],
        ];
    }

    /**
     * At-will employment : pas de préavis légal (sauf contrats/accords).
     */
    public function noticePeriodDays(float $yearsOfService, ?string $category = null): float
    {
        return 0.0;
    }

    /**
     * Pas d'indemnité de licenciement statutaire (WARN ne couvre que les
     * licenciements collectifs, hors moteur) — override explicite du défaut
     * historique 1,0 mois/an.
     */
    public function severanceMonthsPerYear(float $yearsOfService): float
    {
        return 0.0;
    }
}
