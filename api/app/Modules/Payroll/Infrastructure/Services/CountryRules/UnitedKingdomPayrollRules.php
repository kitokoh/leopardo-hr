<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

/**
 * United Kingdom (GB) — PAYE 2026-27 + National Insurance Class 1 2026-27.
 *
 * Audit légal 2026-08-24 (sources publiques) :
 *  - PAYE : HMRC — personal allowance £12 570 (gelée jusqu'en 2027-28),
 *    basic rate 20 % (£12 571–50 270), higher rate 40 % (£50 271–125 140),
 *    additional rate 45 % (> £125 140).
 *  - NI Class 1 2026-27 : employé 8 % entre le Primary Threshold
 *    (£12 570/an) et l'Upper Earnings Limit (£50 270/an), puis 2 % au-delà ;
 *    employeur 15 % au-delà du Secondary Threshold (£5 000/an, inchangé
 *    depuis avril 2025 — Budget d'automne 2024).
 *  - National Living Wage : £12,71/h (21+, 1er avril 2026 — GOV.UK).
 *  - Working Time Regulations 1998 : plafond légal de 48 h/semaine ; le
 *    Royaume-Uni n'impose AUCUNE majoration légale des heures
 *    supplémentaires (taux contractuels uniquement).
 *  - Pension auto-enrolment (5 % sal. / 3 % pat. minimum sur les qualifying
 *    earnings) et SSP (Statutory Sick Pay £118,75/semaine) : documentés,
 *    non modélisés dans le moteur (pilot).
 *
 * Confidence : pilot — à valider par un expert-comptable local (UK payroll
 * provider / HMRC-recognised) avant passage en `production`.
 */
class UnitedKingdomPayrollRules extends AbstractCountryRules
{
    /** Primary Threshold annuel (NI employé, 2026-27). */
    private const NI_PRIMARY_THRESHOLD = 12570.0;

    /** Upper Earnings Limit annuel (NI employé, 2026-27). */
    private const NI_UPPER_EARNINGS_LIMIT = 50270.0;

    /** Secondary Threshold annuel (NI employeur, 2026-27). */
    private const NI_SECONDARY_THRESHOLD = 5000.0;

    public function countryCode(): string
    {
        return 'GB';
    }

    public function currency(): string
    {
        return 'GBP';
    }

    public function minimumWage(): float
    {
        // NLW 21+ £12,71/h × 173,33 h mensuelles (équivalent temps plein)
        // ≈ £2 203,02 → 2 203,00.
        return 2203.0;
    }

    public function socialContributions(): array
    {
        return [
            // NI Class 1 employé — taux principal 8 % entre le PT mensuel
            // (£1 047,50) et l'UEL mensuel (£4 189,17). L'exonération PT est
            // appliquée dans calculateSocialCharges (bande dégressive), le
            // `cap` = UEL mensuel sert d'information + de borne de simulation.
            ['name' => 'National Insurance employee (main rate)', 'code' => 'NI_GB_EMP', 'type' => 'employee', 'rate' => 8.0, 'cap' => 4189.17],
            // NI Class 1 employé — taux supérieur 2 % au-delà de l'UEL.
            ['name' => 'National Insurance employee (higher rate)', 'code' => 'NI_GB_EMP_HI', 'type' => 'employee', 'rate' => 2.0, 'cap' => null],
            // NI Class 1 employeur — 15 % au-delà du Secondary Threshold
            // mensuel (£416,67). Employment Allowance (£10 500/an) : relief
            // annuel par employeur, non modélisé par bulletin (documenté).
            ['name' => 'National Insurance employer', 'code' => 'NI_GB_PAT', 'type' => 'employer', 'rate' => 15.0, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        // Barème PAYE ANNUEL 2026-27. La personal allowance est portée par
        // la première tranche à 0 % (bornes inclusives du helper progressif).
        return [
            ['min' => 0, 'max' => 12570, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 12571, 'max' => 50270, 'rate' => 20, 'fixed_deduction' => 0],
            ['min' => 50271, 'max' => 125140, 'rate' => 40, 'fixed_deduction' => 0],
            ['min' => 125141, 'max' => null, 'rate' => 45, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        $annualTaxable = $grossTaxable * $annualBasis;
        $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

        return round($tax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        $pt = self::NI_PRIMARY_THRESHOLD / 12; // £1 047,50/mois
        $uel = self::NI_UPPER_EARNINGS_LIMIT / 12; // £4 189,17/mois
        $st = self::NI_SECONDARY_THRESHOLD / 12; // £416,67/mois

        $mainRate = $this->resolveContributionRate('NI_GB_EMP', 8.0);
        $higherRate = $this->resolveContributionRate('NI_GB_EMP_HI', 2.0);
        $employerRate = $this->resolveContributionRate('NI_GB_PAT', 15.0);

        // Mode simulation « sans plafond légal » (#1815) : l'UEL cesse de
        // borner la bande principale (tout le brut reste au taux principal).
        $uelBase = $this->capsEnabled() ? min($grossSalary, $uel) : $grossSalary;
        $mainBand = max(0.0, $uelBase - $pt);
        $higherBand = $this->capsEnabled() ? max(0.0, $grossSalary - $uel) : 0.0;

        return [
            'employee' => round($mainBand * $mainRate / 100 + $higherBand * $higherRate / 100, 2),
            'employer' => round(max(0.0, $grossSalary - $st) * $employerRate / 100, 2),
        ];
    }

    public function timezone(): string
    {
        return 'Europe/London';
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
        return 'England and Wales bank holidays — 8 fixed/variable dates per year, published by GOV.UK (PA2-COUNTRY-012).';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['GB'].
     */
    public function language(): string
    {
        return 'en';
    }

    public function complianceWarning(): string
    {
        return 'Pilot ruleset for the United Kingdom: PAYE bands, National Insurance Class 1 rates and thresholds are sourced from HMRC public guidance (2026-27) but are NOT a substitute for a certified UK payroll provider (RTI/FPS filing) or local tax counsel. Statutory Sick Pay and auto-enrolment pension minimums are documented but not modelled. Do not rely on this for statutory payroll compliance without validation.';
    }

    /**
     * Working Time Regulations 1998 — 48 h/semaine (plafond légal, avec
     * opt-out individuel). Pas de majoration légale des HS au Royaume-Uni.
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 48.0;
    }

    /**
     * Pas de palier de majoration LÉGAL (taux contractuels uniquement) —
     * contrat vide volontaire : le moteur n'injecte aucune majoration par
     * défaut pour ce pays.
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [];
    }

    /**
     * Employment Rights Act 1996 s.86 : préavis légal minimum — 1 semaine
     * par année complète d'ancienneté, plafonné à 12 semaines, exigible
     * après 1 mois d'emploi.
     */
    public function noticePeriodDays(float $yearsOfService, ?string $category = null): float
    {
        if ($yearsOfService < 1 / 12) {
            return 0.0;
        }

        if ($yearsOfService < 1.0) {
            return 7.0;
        }

        return min(12.0, floor($yearsOfService)) * 7.0;
    }

    /**
     * Redundancy Payments Act 1996 : indemnité statutaire de licenciement
     * économique — 0,5 semaine (< 22 ans), 1 semaine (22–40), 1,5 semaine
     * (41+), plafonnée à 20 années et à un plafond hebdomadaire (~£700).
     * Approximation pilote : 1 semaine par année (tranche 22–40 ans) ≈
     * 0,2309 mois — l'âge et le plafond hebdo ne sont pas modélisés.
     */
    public function severanceMonthsPerYear(float $yearsOfService): float
    {
        return 0.2309;
    }

    /**
     * Statutory Sick Pay (SSP) 2026-27 : 3 jours de carence puis £118,75 par
     * semaine (taux forfaitaire — non exprimable en fraction du salaire dans
     * le contrat du moteur) → politique documentée, aucune IJ modélisée.
     *
     * @return array{
     *     waiting_days: int,
     *     daily_allowance_rates: array<int, array{from_day: int, to_day: int|null, rate: float}>,
     *     max_paid_days: int,
     *     employer_maintenance_days: int,
     * }
     */
    public function sickLeavePolicy(): array
    {
        return [
            'waiting_days' => 3,
            'daily_allowance_rates' => [],
            'max_paid_days' => 0,
            'employer_maintenance_days' => 0,
        ];
    }
}
