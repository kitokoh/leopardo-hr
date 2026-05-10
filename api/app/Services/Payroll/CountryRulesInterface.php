<?php

namespace App\Services\Payroll;

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
}
