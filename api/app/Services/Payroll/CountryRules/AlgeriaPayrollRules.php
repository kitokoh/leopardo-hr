<?php

namespace App\Services\Payroll\CountryRules;

use App\Services\Payroll\CountryRulesInterface;

class AlgeriaPayrollRules implements CountryRulesInterface
{
    public function countryCode(): string
    {
        return 'DZ';
    }

    public function currency(): string
    {
        return 'DZD';
    }

    public function minimumWage(): float
    {
        return 20000.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'CNAS Salariale', 'code' => 'CNAS_EMP', 'type' => 'employee', 'rate' => 9.0, 'cap' => null],
            ['name' => 'CNAS Patronale', 'code' => 'CNAS_PAT', 'type' => 'employer', 'rate' => 26.0, 'cap' => null],
        ];
    }

    public function taxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 20000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 20001, 'max' => 40000, 'rate' => 23, 'fixed_deduction' => 0],
            ['min' => 40001, 'max' => 80000, 'rate' => 27, 'fixed_deduction' => 0],
            ['min' => 80001, 'max' => 160000, 'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 160001, 'max' => 320000, 'rate' => 33, 'fixed_deduction' => 0],
            ['min' => 320001, 'max' => null, 'rate' => 35, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12): float
    {
        $monthlyTaxable = $grossTaxable;
        $tax = 0.0;

        foreach ($this->taxSlabs() as $slab) {
            if ($monthlyTaxable <= 0) {
                break;
            }
            $max = $slab['max'] ?? PHP_FLOAT_MAX;
            $taxable = min($monthlyTaxable, $max - $slab['min'] + 1);
            if ($taxable > 0) {
                $tax += $taxable * ($slab['rate'] / 100);
            }
            $monthlyTaxable -= $taxable;
        }

        $annualTax = $tax * $annualBasis;
        $abatement = min(max($annualTax * 0.40, 12000), 18000);
        $finalAnnualTax = max(0, $annualTax - $abatement);

        return round($finalAnnualTax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        return [
            'employee' => round($grossSalary * 0.09, 2),
            'employer' => round($grossSalary * 0.26, 2),
        ];
    }
}
