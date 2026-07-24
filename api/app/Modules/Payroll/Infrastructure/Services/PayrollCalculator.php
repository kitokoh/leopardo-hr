<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Domain\Models\SalaryComponent;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AbstractCountryRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CanadaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PayrollCalculator
{
    /** @var array<string, CountryRulesInterface> */
    private array $rulesMap;

    /**
     * @param  iterable<CountryRulesInterface>  $countryRules
     */
    public function __construct(iterable $countryRules = [])
    {
        $this->rulesMap = [];

        foreach ($countryRules as $rule) {
            $this->rulesMap[$rule->countryCode()] = $rule;
        }

        if ($this->rulesMap === []) {
            $this->rulesMap = [
                'DZ' => new AlgeriaPayrollRules,
                'MA' => new MoroccoPayrollRules,
                'TN' => new TunisiaPayrollRules,
                'FR' => new FrancePayrollRules,
                'TR' => new TurkeyPayrollRules,
                'SN' => new SenegalPayrollRules,
            ];

            // CEMAC zone (PA2-COUNTRY-007): one CemacPayrollRules instance per
            // member state, each scoped via forMemberCountry() so countryCode()
            // returns the actual ISO 3166-1 alpha-2 code (CemacPayrollRules is
            // a single class covering all six members, not six separate ones).
            foreach (CemacPayrollRules::MEMBER_COUNTRY_CODES as $memberCountryCode) {
                $this->rulesMap[$memberCountryCode] = (new CemacPayrollRules)->forMemberCountry($memberCountryCode);
            }

            // CEDEAO/UEMOA zone (PA2-COUNTRY-008): same pattern as CEMAC above,
            // one CedeaoPayrollRules instance per XOF member state (Senegal
            // already has its own dedicated SenegalPayrollRules and is not
            // duplicated here).
            foreach (CedeaoPayrollRules::MEMBER_COUNTRY_CODES as $memberCountryCode) {
                $this->rulesMap[$memberCountryCode] = (new CedeaoPayrollRules)->forMemberCountry($memberCountryCode);
            }

            // Canada (PA2-COUNTRY-009): single ISO country code CA, province
            // is an optional refinement (timezone/overtime threshold) rather
            // than a separate registered country code — see CanadaPayrollRules
            // docblock. Federal defaults (no province) are registered here;
            // callers with a known province should use forProvince().
            $this->rulesMap['CA'] = new CanadaPayrollRules;
        }
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
        $companyId = $run->company_id;
        $rules = $this->getRules($run->country_code);
        // Scope the rules to this company so any company-specific TaxSlab/
        // SocialContribution overrides configured via TaxSlabController/
        // SocialContributionController are actually applied (see
        // AbstractCountryRules::forCompany()). Falls back to global
        // (company_id IS NULL) rows, then to the hardcoded defaults.
        //
        // Also scope to the run's own period_start (PA2-ARCH-004): country
        // tax slabs/social contributions are associated with an effective
        // date, so recalculating a past run (e.g. for an audit) resolves
        // the rates that were effective *during that run's own period*,
        // not today's rates. This makes calculateRun() safe to call again
        // on an old run without silently drifting its figures forward to
        // whatever rates happen to be current today.
        if ($rules instanceof AbstractCountryRules) {
            $rules = $rules->forCompany($companyId)->asOf($run->period_start);
        }

        /** @var Collection<int, Employee> $employees */
        $employees = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->get();

        /** @var Collection<int, SalaryStructure> $structuresCollection */
        $structuresCollection = SalaryStructure::where('company_id', $companyId)
            ->where('country_code', $run->country_code)
            ->where('active', true)
            ->with('components')
            ->get();

        /** @var Collection<int|string, SalaryStructure> $structures */
        $structures = $structuresCollection->keyBy('id');

        /** @var SalaryStructure|null $defaultStructure */
        $defaultStructure = $structures->first();

        DB::transaction(function () use ($run, $employees, $defaultStructure, $rules) {
            $run->paySlips()->delete();

            $totalGross = 0.0;
            $totalDeductions = 0.0;
            $totalNet = 0.0;
            $totalEmployerCost = 0.0;

            foreach ($employees as $employee) {
                /** @var SalaryStructure|null $structure */
                $structure = $defaultStructure;

                if (! $structure) {
                    continue;
                }

                $slip = $this->calculateSlip($run, $employee, $structure, $rules);

                $totalGross += (float) $slip->gross_salary;
                $totalDeductions += (float) $slip->total_deductions;
                $totalNet += (float) $slip->net_salary;
                $totalEmployerCost += (float) $slip->total_cost;
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

        /** @var Collection<int, SalaryComponent> $components */
        $components = $structure->components->where('active', true)->sortBy('order');
        foreach ($components as $component) {
            /** @var SalaryComponent $component */
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

        /** @var array{employee: float, employer: float} $social */
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
            /** @var SalaryComponent $component */
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

        /** @var PaySlip $slip */
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
