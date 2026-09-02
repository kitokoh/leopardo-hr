<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\Payroll\Domain\Models\Payroll;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6534 (audit surface API E1/E2) — RBAC paie :
 * /payrolls et /salary-advances étaient lisibles par TOUT manager sans
 * scope équipe (un manager dept/superviseur énumérait les salaires bruts/
 * nets et les avances de toute la société). Scope équipe appliqué via le
 * pattern visibleToManager (PA2-SEC-002/003).
 */
class PayrollTeamScopeTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function deptManager(Company $company, int $departmentId): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->managerDept()->create([
            'company_id' => $company->id,
            'department_id' => $departmentId,
        ]);

        return $manager;
    }

    public function test_dept_manager_only_sees_his_team_on_payrolls(): void
    {
        $company = $this->company();
        /** @var Department $ownDept */
        $ownDept = Department::create(['company_id' => $company->id, 'name' => 'Ma direction']);
        /** @var Department $otherDept */
        $otherDept = Department::create(['company_id' => $company->id, 'name' => 'Autre service']);

        $deptManager = $this->deptManager($company, (int) $ownDept->id);
        /** @var Employee $teamMember */
        $teamMember = Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => $ownDept->id,
        ]);
        /** @var Employee $outside */
        $outside = Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => $otherDept->id,
        ]);

        Payroll::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $teamMember->id,
            'period_month' => 8,
            'period_year' => 2026,
            'gross_salary' => 100000,
            'net_salary' => 75000,
            'status' => 'validated',
        ]);
        Payroll::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $outside->id,
            'period_month' => 8,
            'period_year' => 2026,
            'gross_salary' => 999999,
            'net_salary' => 888888,
            'status' => 'validated',
        ]);

        Sanctum::actingAs($deptManager);

        $this->getJson('/api/v1/payrolls')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $teamMember->id);
    }

    public function test_dept_manager_cannot_show_out_of_team_payroll(): void
    {
        $company = $this->company();
        /** @var Department $ownDept */
        $ownDept = Department::create(['company_id' => $company->id, 'name' => 'Ma direction']);
        /** @var Department $otherDept */
        $otherDept = Department::create(['company_id' => $company->id, 'name' => 'Autre service']);

        $deptManager = $this->deptManager($company, (int) $ownDept->id);
        /** @var Employee $outside */
        $outside = Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => $otherDept->id,
        ]);

        $payroll = Payroll::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $outside->id,
            'period_month' => 8,
            'period_year' => 2026,
            'gross_salary' => 999999,
            'net_salary' => 888888,
            'status' => 'validated',
        ]);

        Sanctum::actingAs($deptManager);

        $this->getJson("/api/v1/payrolls/{$payroll->id}")->assertForbidden();
    }

    public function test_dept_manager_only_sees_his_team_on_salary_advances(): void
    {
        $company = $this->company();
        /** @var Department $ownDept */
        $ownDept = Department::create(['company_id' => $company->id, 'name' => 'Ma direction']);
        /** @var Department $otherDept */
        $otherDept = Department::create(['company_id' => $company->id, 'name' => 'Autre service']);

        $deptManager = $this->deptManager($company, (int) $ownDept->id);
        /** @var Employee $teamMember */
        $teamMember = Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => $ownDept->id,
        ]);
        /** @var Employee $outside */
        $outside = Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => $otherDept->id,
        ]);

        SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $teamMember->id,
            'amount' => 500,
            'status' => 'pending',
        ]);
        SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $outside->id,
            'amount' => 9000,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($deptManager);

        $this->getJson('/api/v1/salary-advances')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $teamMember->id);
    }

    public function test_dept_manager_cannot_show_out_of_team_salary_advance(): void
    {
        $company = $this->company();
        /** @var Department $ownDept */
        $ownDept = Department::create(['company_id' => $company->id, 'name' => 'Ma direction']);
        /** @var Department $otherDept */
        $otherDept = Department::create(['company_id' => $company->id, 'name' => 'Autre service']);

        $deptManager = $this->deptManager($company, (int) $ownDept->id);
        /** @var Employee $outside */
        $outside = Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => $otherDept->id,
        ]);

        $advance = SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $outside->id,
            'amount' => 9000,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($deptManager);

        $this->getJson("/api/v1/salary-advances/{$advance->id}")->assertForbidden();
    }
}
