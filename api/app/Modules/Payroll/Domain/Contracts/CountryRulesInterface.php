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

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis): float;

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
}
