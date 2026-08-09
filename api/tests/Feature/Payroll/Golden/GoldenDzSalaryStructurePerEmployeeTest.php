<?php

namespace Tests\Feature\Payroll\Golden;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Tests\TestCase;

/**
 * Programme FOCUS — F-05/F-20 (issue #1587) : chaque employé est payé sur SA
 * structure salariale (employees.salary_structure_id), pas sur la première
 * structure active de l'entreprise.
 *
 * Golden : 2 structures, 2 employés affectés différemment, 1 non affecté →
 * montants distincts + repli sur la structure par défaut.
 */
class GoldenDzSalaryStructurePerEmployeeTest extends TestCase
{
    use \Tests\RefreshTenantDatabase;

    public function test_employees_are_paid_on_their_own_salary_structure(): void
    {
        $company = Company::factory()->create();

        $structureA = SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Cadre — 120 000',
            'base_salary' => 120000.0,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        $structureB = SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Agent — 60 000',
            'base_salary' => 60000.0,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        // L'employé A est sur la structure A (120 000), le B sur la structure
        // B (60 000), le C n'a pas d'affectation → repli sur la première
        // structure active (A, 120 000) — comportement historique #1587.
        $employeeA = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_structure_id' => $structureA->id,
        ]);
        $employeeB = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_structure_id' => $structureB->id,
        ]);
        $employeeC = Employee::factory()->create(['company_id' => $company->id]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
            'total_gross' => 0,
            'total_net' => 0,
            'employee_count' => 0,
        ]);

        (new PayrollCalculator())->calculateRun($run);

        $slips = $run->paySlips()->get()->keyBy('employee_id');

        $this->assertSame(3, $slips->count());
        $this->assertSame(120000.0, (float) $slips[$employeeA->id]->gross_salary, 'Employé A : structure 120 000');
        $this->assertSame(60000.0, (float) $slips[$employeeB->id]->gross_salary, 'Employé B : structure 60 000');
        $this->assertSame(120000.0, (float) $slips[$employeeC->id]->gross_salary, 'Employé C : repli structure par défaut');

        // Le total du run reflète les trois structures distinctes.
        $this->assertSame(300000.0, (float) $run->fresh()->total_gross);
    }
}
