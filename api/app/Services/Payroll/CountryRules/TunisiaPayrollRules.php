<?php

namespace App\Services\Payroll\CountryRules;

use App\Services\Payroll\CountryRulesInterface;

class TunisiaPayrollRules implements CountryRulesInterface
{
    public function countryCode(): string
    {
        return 'TN';
    }

    public function currency(): string
    {
        return 'TND';
    }

    public function minimumWage(): float
    {
        return 480.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'CNSS Salariale', 'code' => 'CNSS_TN_EMP', 'type' => 'employee', 'rate' => 9.18, 'cap' => null],
            ['name' => 'CNSS Patronale', 'code' => 'CNSS_TN_PAT', 'type' => 'employer', 'rate' => 16.57, 'cap' => null],
        ];
    }

    public function taxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 5000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 5001, 'max' => 20000, 'rate' => 26, 'fixed_deduction' => 0],
            ['min' => 20001, 'max' => 30000, 'rate' => 28, 'fixed_deduction' => 0],
            ['min' => 30001, 'max' => 50000, 'rate' => 32, 'fixed_deduction' => 0],
            ['min' => 50001, 'max' => null, 'rate' => 35, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12): float
    {
        $annualTaxable = $grossTaxable * $annualBasis;
        $remaining = $annualTaxable;
        $tax = 0.0;

        foreach ($this->taxSlabs() as $slab) {
            if ($remaining <= 0) {
                break;
            }
            $max = $slab['max'] ?? PHP_FLOAT_MAX;
            $width = $max - $slab['min'] + 1;
            $taxable = min($remaining, $width);
            $tax += $taxable * ($slab['rate'] / 100);
            $remaining -= $taxable;
        }

        return round($tax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        return [
            'employee' => round($grossSalary * 0.0918, 2),
            'employer' => round($grossSalary * 0.1657, 2),
        ];
    }
}
