<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Contracts;

interface CountryRulesInterface
{
    public function countryCode(): string;

    public function currency(): string;

    public function minimumWage(): float;

    /**
     * @return array<int, array{name: string, code: string, type: string, rate: float, cap: float|null}>
     */
    public function socialContributions(): array;

    /**
     * @return array<int, array{min: float, max: float|null, rate: float, fixed_deduction: float}>
     */
    public function taxSlabs(): array;

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12): float;

    /**
     * @return array{employee: float, employer: float}
     */
    public function calculateSocialCharges(float $grossSalary): array;

    /**
     * Timezone used for scheduling/attendance calculations in this country
     * (IANA identifier, e.g. "Africa/Algiers").
     */
    public function timezone(): string;

    /**
     * Default weekly rest day(s) as ISO-8601 weekday integers (1=Monday .. 7=Sunday).
     * Used as the payroll/attendance default when a company has not configured
     * its own rest-day schedule.
     *
     * @return array<int, int>
     */
    public function weeklyRestDays(): array;

    /**
     * Pay cycles this country's rules currently support (e.g. 'daily',
     * 'weekly', 'monthly'). Not every country supports every cycle yet;
     * callers should reject unsupported cycles instead of assuming monthly.
     *
     * @return array<int, string>
     */
    public function supportedPayCycles(): array;

    /**
     * Human-readable disclosure of where public-holiday dates come from for
     * this country. Intentionally explicit about placeholder status rather
     * than silently returning a fabricated/guessed holiday calendar.
     */
    public function publicHolidaysSource(): string;

    /**
     * Maturity of this country's ruleset: 'production' (legally validated
     * and in real payroll use), 'pilot' (implemented from public sources,
     * not yet legally validated locally), or 'placeholder' (structure only,
     * values not yet researched).
     */
    public function confidenceLevel(): string;

    /**
     * Default language for this country as an ISO 639-1 code (e.g. "fr",
     * "tr", "en"), matching App\Support\CountryDefaults so payroll-facing
     * consumers (payslip generation, notices, provisioning) can resolve a
     * default locale without a separate lookup.
     */
    public function language(): string;

    /**
     * Human-readable compliance disclaimer for this country's payroll rules,
     * explicit about confidenceLevel() so a manager/platform admin can't
     * mistake pilot/placeholder values (tax slabs, social contributions,
     * overtime thresholds) for locally validated statutory figures. Distinct
     * from publicHolidaysSource(), which is scoped to holiday-calendar
     * disclosure only.
     */
    public function complianceWarning(): string;

    /**
     * Standard legal weekly working-hours threshold beyond which overtime
     * applies by default, absent a company-specific schedule override (see
     * Schedule::overtime_threshold_weekly for the per-company setting this
     * country default seeds).
     */
    public function overtimeThresholdWeeklyHours(): float;

    /**
     * Legal overtime premium tiers, evaluated in order against hours worked
     * beyond overtimeThresholdWeeklyHours(). 'up_to_hours' is the width of
     * that tier (null = unbounded/last tier), 'multiplier' is the pay
     * multiplier applied to hours falling in that tier (e.g. 1.5 = +50%).
     * Pilot-sourced from each country's general labor code baseline; real
     * payroll runs should confirm against confidenceLevel() before relying
     * on this for statutory compliance.
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array;

    /**
     * Legal notice period (délai de préavis / preaviso) in days, given the
     * employee's years of service. Used to compute the compensatory notice
     * pay when the notice is not served (indemnité compensatrice de préavis).
     * Country rules own the legal duration; 0 means "no statutory notice pay
     * by default — the contract/execution decides" (historic Leopardo
     * behaviour, F-08). FOCUS 2 (F-31).
     */
    public function noticePeriodDays(float $yearsOfService): float;

    /**
     * Severance months of base salary per year of service (indemnité de
     * licenciement / indemnité d'ancienneté, solde de tout compte). May be
     * tiered by seniority in some countries — implementations must return
     * the value applicable for the given yearsOfService. Historic default:
     * 1.0 month per year (F-08). FOCUS 2 (F-31).
     */
    public function severanceMonthsPerYear(float $yearsOfService): float;

    /**
     * Professional-expenses deduction (abattement frais professionnels)
     * applied on top of employee social contributions when computing the
     * income-tax base, where the country's tax code provides one.
     *
     * @return array{rate: float, cap: float|null} rate in percentage points
     *                                             (e.g. 30.0 = 30 % of gross), cap as an absolute monthly ceiling
     *                                             in the country currency (null = no ceiling). Default: no
     *                                             deduction (rate 0.0) — countries with a legal abatement
     *                                             override this (CM 30 % capped, CI, SN...). ZONE-INFRA (#1820).
     */
    public function professionalExpensesDeduction(): array;

    /**
     * Flat/minimum bracket tax (taxe de minimum fiscal, e.g. TRIMF in SN /
     * minimum fiscal in CI) computed on gross salary when the country's tax
     * code mandates a minimum contribution even for low incomes. 0.0 means
     * the country has no such tax. The payroll engine injects a
     * "Taxe de minimum fiscal" deduction line when this returns > 0.
     * ZONE-INFRA (#1820).
     */
    public function calculateBracketTax(float $grossSalary): float;

    /**
     * Display label of the flat-tax deduction line injected by the payroll
     * engine when calculateBracketTax() returns > 0. Defaults to
     * "Taxe de minimum fiscal"; countries that use the same mechanism for a
     * differently-named flat tax (e.g. CI's Contribution Nationale, CN)
     * override this so the payslip line is honest. ZONE-INFRA (#1820).
     */
    public function flatPayrollTaxLabel(): string;

    /**
     * Whether the country's labour code legally mandates a 13th month
     * (13ème mois / prima) paid in December (or the configured month).
     * Default: false (contractual practice only). ZONE-INFRA (#1820).
     */
    public function thirteenthMonthMandatory(): bool;

    /**
     * Tax treatment of the mandatory 13th month for income-tax purposes:
     * 'fully_taxable' (default — the 13th month is added to the December
     * gross and taxed like ordinary pay) or 'spread' (taxed over the whole
     * year as if received monthly). ZONE-INFRA (#1820).
     */
    public function thirteenthMonthTaxTreatment(): string;

    /**
     * Family-allowance amount per dependent child (allocations familiales),
     * in the country currency, per month. 0.0 means the country has no
     * employer-funded family allowance (or the scheme is not yet wired into
     * the payroll engine). ZONE-INFRA (#1820).
     */
    public function familyAllowancePerChild(): float;
}
