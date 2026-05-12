<?php

namespace App\Services\Payroll\CountryRules;

class MoroccoPayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'MA';
    }

    public function currency(): string
    {
        return 'MAD';
    }

    public function minimumWage(): float
    {
        return 3111.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'CNSS Salariale', 'code' => 'CNSS_EMP', 'type' => 'employee', 'rate' => 4.48, 'cap' => 6000],
            ['name' => 'CNSS Patronale', 'code' => 'CNSS_PAT', 'type' => 'employer', 'rate' => 8.98, 'cap' => 6000],
            ['name' => 'AMO Salariale', 'code' => 'AMO_EMP', 'type' => 'employee', 'rate' => 2.26, 'cap' => null],
            ['name' => 'AMO Patronale', 'code' => 'AMO_PAT', 'type' => 'employer', 'rate' => 4.11, 'cap' => null],
        ];
    }

    public function taxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 30000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 30001, 'max' => 50000, 'rate' => 10, 'fixed_deduction' => 3000],
            ['min' => 50001, 'max' => 60000, 'rate' => 20, 'fixed_deduction' => 8000],
            ['min' => 60001, 'max' => 80000, 'rate' => 30, 'fixed_deduction' => 14000],
            ['min' => 80001, 'max' => 180000, 'rate' => 34, 'fixed_deduction' => 17200],
            ['min' => 180001, 'max' => null, 'rate' => 38, 'fixed_deduction' => 24400],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12): float
    {
        $annualTaxable = $grossTaxable * $annualBasis;
        $tax = 0.0;
        $fixedDeduction = 0.0;

        foreach ($this->taxSlabs() as $slab) {
            $max = $slab['max'] ?? PHP_FLOAT_MAX;
            if ($annualTaxable >= $slab['min'] && $annualTaxable <= $max) {
                $tax = $annualTaxable * ($slab['rate'] / 100);
                $fixedDeduction = $slab['fixed_deduction'];
                break;
            }
        }

        return round(max(0, ($tax - $fixedDeduction)) / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        $cnssBase = min($grossSalary, 6000);

        return [
            'employee' => round($cnssBase * 0.0448 + $grossSalary * 0.0226, 2),
            'employer' => round($cnssBase * 0.0898 + $grossSalary * 0.0411, 2),
        ];
    }
}
