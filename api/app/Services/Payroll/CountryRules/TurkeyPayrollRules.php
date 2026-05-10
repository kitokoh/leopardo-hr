<?php

namespace App\Services\Payroll\CountryRules;

use App\Services\Payroll\CountryRulesInterface;

class TurkeyPayrollRules implements CountryRulesInterface
{
    public function countryCode(): string
    {
        return 'TR';
    }

    public function currency(): string
    {
        return 'TRY';
    }

    public function minimumWage(): float
    {
        return 20002.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'SGK Salariale', 'code' => 'SGK_TR_EMP', 'type' => 'employee', 'rate' => 14.0, 'cap' => null],
            ['name' => 'SGK Patronale', 'code' => 'SGK_TR_PAT', 'type' => 'employer', 'rate' => 20.5, 'cap' => null],
            ['name' => 'Chomage Salariale', 'code' => 'UNEMP_TR_EMP', 'type' => 'employee', 'rate' => 1.0, 'cap' => null],
            ['name' => 'Chomage Patronale', 'code' => 'UNEMP_TR_PAT', 'type' => 'employer', 'rate' => 2.0, 'cap' => null],
        ];
    }

    public function taxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 110000, 'rate' => 15, 'fixed_deduction' => 0],
            ['min' => 110001, 'max' => 230000, 'rate' => 20, 'fixed_deduction' => 0],
            ['min' => 230001, 'max' => 580000, 'rate' => 27, 'fixed_deduction' => 0],
            ['min' => 580001, 'max' => 3000000, 'rate' => 35, 'fixed_deduction' => 0],
            ['min' => 3000001, 'max' => null, 'rate' => 40, 'fixed_deduction' => 0],
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
            'employee' => round($grossSalary * 0.15, 2),
            'employer' => round($grossSalary * 0.225, 2),
        ];
    }
}
