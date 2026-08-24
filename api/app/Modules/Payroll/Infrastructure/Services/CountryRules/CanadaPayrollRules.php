<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

/**
 * Canada (CA): unlike CemacPayrollRules/CedeaoPayrollRules, Canada is a
 * single ISO 3166-1 alpha-2 country code, so countryCode() always returns
 * 'CA' regardless of province — the province is an *optional* refinement
 * (PA2-COUNTRY-009 acceptance criteria: "CAD province optionnelle timezone
 * placeholders overtime provinciaux"), not a separate persisted country
 * code. Provincial/territorial employment-standards legislation in Canada
 * differs mainly on: timezone (provinces span 6 IANA zones) and the
 * statutory weekly overtime threshold (federal Canada Labour Code default
 * is 44h/week; several provinces set a lower 40h/week threshold instead).
 *
 * Audit légal 2026-08-24 (pack EN #5255, audit complémentaire — sources
 * CRA/Canada.ca) :
 *  - Barème fédéral IR 2026 : 14 % (taux le plus bas réduit de 15 % →
 *    14 % au 1er juillet 2025, plein effet 2026) jusqu'à $58 523, puis
 *    20,5 % ≤ $117 045, 26 % ≤ $181 440, 29 % ≤ $258 482, 33 % au-delà.
 *  - Basic Personal Amount 2026 : $16 452 (revenu ≤ $181 440), élimination
 *    progressive entre $181 440 et $258 482 jusqu'à $14 829 — appliqué en
 *    crédit non remboursable (BPA × 14 %).
 *  - CPP 2026 : 5,95 % (sal. et pat.) sur le YMPE $74 600 (exemption de
 *    base $3 500) ; CPP2 : 4 % entre $74 600 et le YAMPE $85 000.
 *  - EI 2026 : 1,63 % salarial (1,4× = 2,282 % patronal) sur la MIE
 *    $68 900.
 *  - Salaire minimum FÉDÉRAL : $18,15/h (1er avril 2026) — les provinces
 *    ont leurs propres minimums (souvent plus élevés), non modélisés.
 *  - Canada Labour Code : 44 h/semaine (fédéral) ; provinces 40-48 h.
 *    Préavis fédéral : 1-8 semaines selon l'ancienneté (CLC art. 230).
 */
class CanadaPayrollRules extends AbstractCountryRules
{
    /**
     * ISO 3166-2:CA province/territory subdivision codes this class
     * recognizes for forProvince()/the constructor. Passing null (or an
     * unrecognized code) keeps federal Canada Labour Code defaults, per
     * the "province optionnelle" acceptance criterion.
     */
    public const PROVINCE_CODES = ['AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT'];

    /** YMPE 2026 (CPP — premier plafond annuel). */
    private const CPP_YMPE = 74600.0;

    /** YAMPE 2026 (CPP2 — second plafond annuel). */
    private const CPP_YAMPE = 85000.0;

    /** Exemption de base CPP 2026 (annuelle). */
    private const CPP_BASIC_EXEMPTION = 3500.0;

    /** Maximum insurable earnings EI 2026. */
    private const EI_MIE = 68900.0;

    /** BPA maximum 2026 (revenu ≤ $181 440). */
    private const BPA_MAX = 16452.0;

    /** BPA minimum 2026 (revenu ≥ $258 482). */
    private const BPA_MIN = 14829.0;

    /** Début du phase-out du BPA (2026). */
    private const BPA_PHASE_START = 181440.0;

    /** Fin du phase-out du BPA (2026). */
    private const BPA_PHASE_END = 258482.0;

    protected ?string $province = null;

    public function __construct(?string $province = null)
    {
        $this->province = $this->normalizeProvince($province);
    }

    /**
     * Returns a clone scoped to a specific province/territory, so callers
     * that know the employee/company's province get its timezone and
     * statutory overtime threshold instead of the federal default. Pass
     * null to reset to the federal (no-province) default.
     */
    public function forProvince(?string $province): static
    {
        $clone = clone $this;
        $clone->province = $this->normalizeProvince($province);

        return $clone;
    }

    private function normalizeProvince(?string $province): ?string
    {
        if ($province === null || trim($province) === '') {
            return null;
        }

        $normalized = strtoupper(trim($province));

        return in_array($normalized, self::PROVINCE_CODES, true) ? $normalized : null;
    }

    public function countryCode(): string
    {
        return 'CA';
    }

    public function currency(): string
    {
        return 'CAD';
    }

    public function minimumWage(): float
    {
        // Salaire minimum FÉDÉRAL 2026 : $18,15/h × 173,33 h mensuelles
        // ≈ $3 145,94 → 3 146,00. Les minimums provinciaux (souvent plus
        // élevés, ex. BC/ON) ne sont pas modélisés (pilot).
        return 3146.0;
    }

    public function socialContributions(): array
    {
        return [
            // CPP 2026 : 5,95 % chacun sur l'assiette YMPE ($74 600/an →
            // $6 216,67/mois), exemption de base $3 500/an appliquée dans
            // calculateSocialCharges.
            ['name' => 'RPC/CPP salariale', 'code' => 'CPP_CA_EMP', 'type' => 'employee', 'rate' => 5.95, 'cap' => 6216.67],
            ['name' => 'RPC/CPP patronale', 'code' => 'CPP_CA_PAT', 'type' => 'employer', 'rate' => 5.95, 'cap' => 6216.67],
            // CPP2 2026 : 4 % sur l'assiette $74 600 → $85 000 (YAMPE —
            // $7 083,33/mois).
            ['name' => 'RPC2/CPP2 salariale', 'code' => 'CPP2_CA_EMP', 'type' => 'employee', 'rate' => 4.0, 'cap' => 7083.33, 'floor' => 6216.67],
            ['name' => 'RPC2/CPP2 patronale', 'code' => 'CPP2_CA_PAT', 'type' => 'employer', 'rate' => 4.0, 'cap' => 7083.33, 'floor' => 6216.67],
            // AE/EI 2026 : 1,63 % salarial (MIE $68 900 → $5 741,67/mois),
            // 2,282 % patronal (1,4 × 1,63 %).
            ['name' => 'Assurance-emploi salariale', 'code' => 'EI_CA_EMP', 'type' => 'employee', 'rate' => 1.63, 'cap' => 5741.67],
            ['name' => 'Assurance-emploi patronale', 'code' => 'EI_CA_PAT', 'type' => 'employer', 'rate' => 2.282, 'cap' => 5741.67],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        // Barème fédéral 2026 (2 % d'indexation + réduction 15 % → 14 % du
        // taux le plus bas). Bornes inclusives du helper progressif.
        return [
            ['min' => 0, 'max' => 58523, 'rate' => 14, 'fixed_deduction' => 0],
            ['min' => 58524, 'max' => 117045, 'rate' => 20.5, 'fixed_deduction' => 0],
            ['min' => 117046, 'max' => 181440, 'rate' => 26, 'fixed_deduction' => 0],
            ['min' => 181441, 'max' => 258482, 'rate' => 29, 'fixed_deduction' => 0],
            ['min' => 258483, 'max' => null, 'rate' => 33, 'fixed_deduction' => 0],
        ];
    }

    /**
     * Basic Personal Amount 2026 : $16 452 plein (revenu ≤ $181 440),
     * élimination progressive linéaire jusqu'à $14 829 (revenu ≥ $258 482).
     */
    private function basicPersonalAmount(float $annualIncome): float
    {
        if ($annualIncome <= self::BPA_PHASE_START) {
            return self::BPA_MAX;
        }

        if ($annualIncome >= self::BPA_PHASE_END) {
            return self::BPA_MIN;
        }

        $fraction = ($annualIncome - self::BPA_PHASE_START) / (self::BPA_PHASE_END - self::BPA_PHASE_START);

        return self::BPA_MAX - (self::BPA_MAX - self::BPA_MIN) * $fraction;
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        $annualIncome = $grossTaxable * $annualBasis;
        $tax = $this->calculateProgressiveTax($annualIncome, $this->taxSlabs());

        // Le BPA est un crédit d'impôt NON remboursable : BPA × taux le
        // plus bas (14 % en 2026).
        $credit = $this->basicPersonalAmount($annualIncome) * 0.14;

        return round(max(0.0, $tax - $credit) / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        $cppRate = $this->resolveContributionRate('CPP_CA_EMP', 5.95);
        $cpp2Rate = $this->resolveContributionRate('CPP2_CA_EMP', 4.0);
        $eiRate = $this->resolveContributionRate('EI_CA_EMP', 1.63);
        $cppEmployerRate = $this->resolveContributionRate('CPP_CA_PAT', 5.95);
        $cpp2EmployerRate = $this->resolveContributionRate('CPP2_CA_PAT', 4.0);
        $eiEmployerRate = $this->resolveContributionRate('EI_CA_PAT', 2.282);

        $ympe = self::CPP_YMPE / 12; // $6 216,67
        $yampe = self::CPP_YAMPE / 12; // $7 083,33
        $mie = self::EI_MIE / 12; // $5 741,67
        $basicExemption = self::CPP_BASIC_EXEMPTION / 12; // $291,67

        // CPP : (min(brut, YMPE) − exemption de base) × 5,95 %.
        $cppBase = ($this->capsEnabled() ? min($grossSalary, $ympe) : $grossSalary) - $basicExemption;
        $cpp = max(0.0, $cppBase) * $cppRate / 100;

        // CPP2 : 4 % sur la tranche [YMPE, YAMPE].
        $cpp2Base = $this->capsEnabled() ? min($grossSalary, $yampe) - $ympe : 0.0;
        $cpp2 = max(0.0, $cpp2Base) * $cpp2Rate / 100;

        // EI : 1,63 % sur le brut plafonné à la MIE.
        $eiBase = $this->capsEnabled() ? min($grossSalary, $mie) : $grossSalary;
        $ei = $eiBase * $eiRate / 100;
        $eiEmployer = $eiBase * $eiEmployerRate / 100;

        return [
            'employee' => round($cpp + $cpp2 + $ei, 2),
            'employer' => round($cpp + $cpp2 + $eiEmployer, 2),
        ];
    }

    public function timezone(): string
    {
        // Provincial/territorial IANA timezone; falls back to
        // America/Toronto (Ontario, the most populous province) when no
        // province is set, matching the scope doc's "America/Toronto"
        // zone-wide default.
        return match ($this->province) {
            'BC' => 'America/Vancouver',
            'AB' => 'America/Edmonton',
            'SK' => 'America/Regina',
            'MB' => 'America/Winnipeg',
            'QC' => 'America/Toronto', // Eastern, same offset as Ontario
            'NB' => 'America/Moncton',
            'NS' => 'America/Halifax',
            'PE' => 'America/Halifax',
            'NL' => 'America/St_Johns',
            'YT' => 'America/Whitehorse',
            'NT' => 'America/Yellowknife',
            'NU' => 'America/Iqaluit',
            default => 'America/Toronto', // ON or no province set (federal default)
        };
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Sunday is the standard weekly rest day used as the Canada-wide
        // default; provinces do not mandate a specific weekday.
        return [7];
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
        return 'CA fixed federal public holidays (seed PublicHolidaySeeder, issue #2255): 1er jan, 1er juil, 11 nov, '.
            '25 déc + mobiles fédéraux (Good Friday, Victoria Day, Labour Day, Thanksgiving) — les fériés '.
            'provinciaux restent à saisir manuellement (PA2-COUNTRY-012).';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006 follow-up: matches App\Support\CountryDefaults,
     * where CA defaults to English.
     */
    public function language(): string
    {
        return 'en';
    }

    public function complianceWarning(): string
    {
        return 'Pilot ruleset for Canada (federal only): 2026 federal tax brackets (14 % lowest rate), CPP/CPP2 (YMPE $74,600, YAMPE $85,000) and EI (1.63 % on $68,900) are sourced from CRA/Canada.ca public guidance but are NOT a substitute for a certified Canadian payroll provider or local counsel. Provincial income tax and provincial minimum wages are NOT modelled (see CA_COMPLIANCE.md §6). Do not rely on this for statutory payroll compliance without validation.';
    }

    /**
     * PA2-COUNTRY-009: statutory weekly overtime threshold differs by
     * province — this is the provincial variation the acceptance criteria
     * ("overtime provinciaux") calls for. Falls back to the federal Canada
     * Labour Code threshold (44h/week) when no province is set. Pilot-
     * grade sourcing (general employment-standards baselines, not locally
     * legally validated), see confidenceLevel().
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return match ($this->province) {
            'BC', 'MB', 'NL', 'QC', 'NT', 'NU', 'SK', 'YT' => 40.0,
            'NS', 'PE' => 48.0,
            'AB', 'NB', 'ON' => 44.0,
            default => 44.0, // federal Canada Labour Code default
        };
    }

    /**
     * PA2-COUNTRY-009: every supported province/territory (and the federal
     * default) uses a single +50% overtime premium tier beyond the
     * provincial threshold above; the provincial variation lives in the
     * threshold, not the multiplier. Pilot-grade, see confidenceLevel().
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
     * Canada Labour Code art. 230 : préavis fédéral — 1 semaine après
     * 3 mois, 2 après 1 an, puis +1 semaine par année jusqu'à 8 semaines
     * (≥ 8 ans). Les provinces ont leurs propres régimes (non modélisés).
     */
    public function noticePeriodDays(float $yearsOfService, ?string $category = null): float
    {
        return match (true) {
            $yearsOfService < 0.25 => 0.0,
            $yearsOfService < 1.0 => 7.0,
            $yearsOfService < 3.0 => 14.0,
            $yearsOfService < 4.0 => 21.0,
            $yearsOfService < 5.0 => 28.0,
            $yearsOfService < 6.0 => 35.0,
            $yearsOfService < 7.0 => 42.0,
            $yearsOfService < 8.0 => 49.0,
            default => 56.0,
        };
    }

    /**
     * Indemnité de départ statutaire : provinciale (ex. Ontario ESA — 1
     * semaine par année plafonnée 8). Approximation pilote fédérale :
     * 1 semaine par année ≈ 0,2309 mois.
     */
    public function severanceMonthsPerYear(float $yearsOfService): float
    {
        return 0.2309;
    }

    /**
     * Pas de régime fédéral d'indemnisation maladie courte durée (les
     * provinces ont leurs régimes, ex. QPIP au Québec) → politique inerte
     * documentée.
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
            'waiting_days' => 0,
            'daily_allowance_rates' => [],
            'max_paid_days' => 0,
            'employer_maintenance_days' => 0,
        ];
    }
}
