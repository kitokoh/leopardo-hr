<?php

namespace App\Services\Payroll\CountryRules;

class SenegalPayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'SN';
    }

    public function currency(): string
    {
        return 'XOF';
    }

    public function minimumWage(): float
    {
        return 58900.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'IPRES Salariale', 'code' => 'IPRES_SN_EMP', 'type' => 'employee', 'rate' => 5.6, 'cap' => null],
            ['name' => 'IPRES Patronale', 'code' => 'IPRES_SN_PAT', 'type' => 'employer', 'rate' => 8.4, 'cap' => null],
            ['name' => 'CSS Patronale', 'code' => 'CSS_SN_PAT', 'type' => 'employer', 'rate' => 3.0, 'cap' => null],
        ];
    }

    public function taxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 630000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 630001, 'max' => 1500000, 'rate' => 20, 'fixed_deduction' => 0],
            ['min' => 1500001, 'max' => 4000000, 'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 4000001, 'max' => 8000000, 'rate' => 35, 'fixed_deduction' => 0],
            ['min' => 8000001, 'max' => 13500000, 'rate' => 37, 'fixed_deduction' => 0],
            ['min' => 13500001, 'max' => null, 'rate' => 40, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12): float
    {
        $annualTaxable = $grossTaxable * $annualBasis;
        $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

        return round($tax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        return [
            'employee' => round($grossSalary * 0.056, 2),
            'employer' => round($grossSalary * 0.114, 2),
        ];
    }
}
