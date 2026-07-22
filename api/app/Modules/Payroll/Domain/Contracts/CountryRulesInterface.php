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
     * IANA timezone used as the default for companies provisioned in this
     * country (attendance timestamps, payroll cycle boundaries, etc.).
     */
    public function timezone(): string;

    /**
     * ISO-8601 weekday numbers (1 = Monday .. 7 = Sunday) that are the
     * country's default weekly rest days, used to seed company schedules
     * before a manager customizes them.
     *
     * @return array<int, int>
     */
    public function weeklyRestDays(): array;

    /**
     * Payroll cycles (daily/weekly/monthly) this country's rules are known
     * to support. PayrollCycleService and related admin UIs use this to
     * avoid offering a cycle the country rules have not modeled.
     *
     * @return array<int, string>
     */
    public function supportedPayCycles(): array;

    /**
     * Human-readable statement of where the public-holiday calendar for this
     * country comes from. Until an official calendar feed/table is wired in
     * (tracked separately), this documents the placeholder explicitly rather
     * than silently returning an empty or fabricated list.
     */
    public function publicHolidaysSource(): string;

    /**
     * Confidence level for these rules, so platform admin/tenant provisioning
     * can warn operators before they rely on it for real payroll:
     * 'production' (legally validated), 'pilot' (usable, not yet fully
     * validated locally) or 'placeholder' (rough estimate only).
     */
    public function confidenceLevel(): string;
}


