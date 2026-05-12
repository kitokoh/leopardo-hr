<?php

namespace App\Services\Payroll\CountryRules;

class FrancePayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'FR';
    }

    public function currency(): string
    {
        return 'EUR';
    }

    public function minimumWage(): float
    {
        return 1766.0;
    }

    public function socialContributions(): array
    {
        return [
            ['name' => 'Securite sociale salariale', 'code' => 'SS_FR_EMP', 'type' => 'employee', 'rate' => 7.5, 'cap' => null],
            ['name' => 'Securite sociale patronale', 'code' => 'SS_FR_PAT', 'type' => 'employer', 'rate' => 30.0, 'cap' => null],
            ['name' => 'CSG', 'code' => 'CSG_FR', 'type' => 'employee', 'rate' => 9.2, 'cap' => null],
            ['name' => 'CRDS', 'code' => 'CRDS_FR', 'type' => 'employee', 'rate' => 0.5, 'cap' => null],
        ];
    }

    public function taxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 11294, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 11295, 'max' => 28797, 'rate' => 11, 'fixed_deduction' => 0],
            ['min' => 28798, 'max' => 82341, 'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 82342, 'max' => 177106, 'rate' => 41, 'fixed_deduction' => 0],
            ['min' => 177107, 'max' => null, 'rate' => 45, 'fixed_deduction' => 0],
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
        $csgBase = $grossSalary * 0.9825;

        return [
            'employee' => round($grossSalary * 0.075 + $csgBase * 0.092 + $csgBase * 0.005, 2),
            'employer' => round($grossSalary * 0.30, 2),
        ];
    }
}
