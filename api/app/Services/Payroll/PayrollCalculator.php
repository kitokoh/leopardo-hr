<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Models\PaySlipLine;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Services\Payroll\CountryRules\AlgeriaPayrollRules;
use App\Services\Payroll\CountryRules\FrancePayrollRules;
use App\Services\Payroll\CountryRules\MoroccoPayrollRules;
use App\Services\Payroll\CountryRules\SenegalPayrollRules;
use App\Services\Payroll\CountryRules\TunisiaPayrollRules;
use App\Services\Payroll\CountryRules\TurkeyPayrollRules;
use Illuminate\Support\Facades\DB;

class PayrollCalculator
{
    /** @var array<string, CountryRulesInterface> */
    private array $rulesMap;

    public function __construct()
    {
        $this->rulesMap = [
            'DZ' => new AlgeriaPayrollRules,
            'MA' => new MoroccoPayrollRules,
            'TN' => new TunisiaPayrollRules,
            'FR' => new FrancePayrollRules,
            'TR' => new TurkeyPayrollRules,
            'SN' => new SenegalPayrollRules,
        ];
    }

    public function getRules(string $countryCode): CountryRulesInterface
    {
        if (! isset($this->rulesMap[$countryCode])) {
            throw new \InvalidArgumentException("No payroll rules for country: {$countryCode}");
        }

        return $this->rulesMap[$countryCode];
    }

    public function calculateRun(PayrollRun $run): PayrollRun
    {
        $rules = $this->getRules($run->country_code);
        $companyId = $run->company_id;

        $employees = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->get();

        $structures = SalaryStructure::where('company_id', $companyId)
            ->where('country_code', $run->country_code)
            ->where('active', true)
            ->with('components')
            ->get()
            ->keyBy('id');

        $defaultStructure = $structures->first();

        DB::transaction(function () use ($run, $employees, $defaultStructure, $rules) {
            $run->paySlips()->delete();

            $totalGross = 0.0;
            $totalDeductions = 0.0;
            $totalNet = 0.0;
            $totalEmployerCost = 0.0;

            foreach ($employees as $employee) {
                $structure = $defaultStructure;

                if (! $structure) {
                    continue;
                }

                $slip = $this->calculateSlip($run, $employee, $structure, $rules);

                $totalGross += $slip->gross_salary;
                $totalDeductions += $slip->total_deductions;
                $totalNet += $slip->net_salary;
                $totalEmployerCost += $slip->total_cost;
            }

            $run->update([
                'status' => 'calculated',
                'total_gross' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_net' => round($totalNet, 2),
                'total_employer_cost' => round($totalEmployerCost, 2),
                'employee_count' => $run->paySlips()->count(),
                'calculated_at' => now(),
            ]);
        });

        return $run->refresh();
    }

    private function calculateSlip(
        PayrollRun $run,
        Employee $employee,
        SalaryStructure $structure,
        CountryRulesInterface $rules
    ): PaySlip {
        $baseSalary = $structure->base_salary;
        $grossEarnings = $baseSalary;
        $lines = [];
        $order = 0;

        $lines[] = [
            'name' => 'Salaire de base',
            'type' => 'earning',
            'base_amount' => $baseSalary,
            'rate' => null,
            'amount' => $baseSalary,
            'order' => $order++,
        ];

        $components = $structure->components->where('active', true)->sortBy('order');
        foreach ($components as $component) {
            if ($component->type !== 'earning') {
                continue;
            }
            $amount = $this->computeComponentAmount($component, $baseSalary, $grossEarnings);
            $grossEarnings += $amount;
            $lines[] = [
                'salary_component_id' => $component->id,
                'name' => $component->name,
                'type' => 'earning',
                'base_amount' => $baseSalary,
                'rate' => $component->percentage,
                'amount' => $amount,
                'order' => $order++,
            ];
        }

        $social = $rules->calculateSocialCharges($grossEarnings);

        $lines[] = [
            'name' => 'Cotisations salariales',
            'type' => 'deduction',
            'base_amount' => $grossEarnings,
            'rate' => null,
            'amount' => $social['employee'],
            'order' => $order++,
        ];

        $taxableGross = $grossEarnings - $social['employee'];
        $incomeTax = $rules->calculateIncomeTax($taxableGross);

        $lines[] = [
            'name' => 'Impot sur le revenu',
            'type' => 'deduction',
            'base_amount' => $taxableGross,
            'rate' => null,
            'amount' => $incomeTax,
            'order' => $order++,
        ];

        foreach ($components as $component) {
            if ($component->type !== 'deduction') {
                continue;
            }
            $amount = $this->computeComponentAmount($component, $baseSalary, $grossEarnings);
            $lines[] = [
                'salary_component_id' => $component->id,
                'name' => $component->name,
                'type' => 'deduction',
                'base_amount' => $grossEarnings,
                'rate' => $component->percentage,
                'amount' => $amount,
                'order' => $order++,
            ];
        }

        $lines[] = [
            'name' => 'Cotisations patronales',
            'type' => 'employer_contribution',
            'base_amount' => $grossEarnings,
            'rate' => null,
            'amount' => $social['employer'],
            'order' => $order++,
        ];

        $totalDeductions = $social['employee'] + $incomeTax;
        foreach ($lines as $line) {
            if ($line['type'] === 'deduction' && $line['name'] !== 'Cotisations salariales' && $line['name'] !== 'Impot sur le revenu') {
                $totalDeductions += $line['amount'];
            }
        }

        $netSalary = round(max(0, $grossEarnings - $totalDeductions), 2);
        $totalCost = round($grossEarnings + $social['employer'], 2);

        $slip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => round($grossEarnings, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => $netSalary,
            'employer_contributions' => round($social['employer'], 2),
            'total_cost' => $totalCost,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'calculated',
        ]);

        foreach ($lines as $line) {
            PaySlipLine::create(array_merge($line, ['pay_slip_id' => $slip->id]));
        }

        return $slip;
    }

    private function computeComponentAmount(SalaryComponent $component, float $baseSalary, float $grossSalary): float
    {
        return match ($component->calculation_type) {
            'fixed' => round((float) $component->amount, 2),
            'percentage_of_base' => round($baseSalary * ((float) $component->percentage / 100), 2),
            'percentage_of_gross' => round($grossSalary * ((float) $component->percentage / 100), 2),
            default => 0.0,
        };
    }
}
