<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Domain\Models\Position;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6545 (audit surface API M2) — 5 endpoints paie/offboarding
 * gardés par `isManager()` seul :
 *   GET /employees/{id}/balance
 *   GET /employees/{id}/ledger
 *   GET /employees/{id}/end-of-contract
 *   GET /employees/{id}/certificate-of-employment
 *   GET /employees/{id}/departure/notice
 *
 * Garde mutualisée via EmployeePolicy::view (visibleToManager) : un
 * manager dept/superviseur ne lit ces données que pour SON équipe.
 */
class PayrollSensitiveEndpointsTeamScopeTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $deptManager;

    private Employee $teamMember;

    private Employee $outsideEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow(Carbon::parse('2026-07-15T12:00:00+00:00'));

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Department $ownDept */
        $ownDept = Department::create(['company_id' => $company->id, 'name' => 'Ma direction']);
        /** @var Department $otherDept */
        $otherDept = Department::create(['company_id' => $company->id, 'name' => 'Autre service']);

        /** @var Employee $deptManager */
        $deptManager = Employee::factory()->managerDept()->create([
            'company_id' => $company->id,
            'department_id' => $ownDept->id,
        ]);
        $this->deptManager = $deptManager;

        /** @var Position $position */
        $position = Position::create(['company_id' => $company->id, 'name' => 'Développeur']);

        /** @var Employee $teamMember */
        $teamMember = Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => $ownDept->id,
            'contract_start' => '2023-07-01',
            'salary_base' => 60000,
            'position_id' => $position->id,
        ]);
        $this->teamMember = $teamMember;

        /** @var Employee $outside */
        $outside = Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => $otherDept->id,
            'contract_start' => '2023-07-01',
            'salary_base' => 60000,
            'position_id' => $position->id,
        ]);
        $this->outsideEmployee = $outside;

        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Cadre moyen DZ',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_dept_manager_reads_own_team_sensitive_endpoints(): void
    {
        Sanctum::actingAs($this->deptManager);

        // Endpoints = 200 pour un collaborateur de SON équipe.
        $this->getJson('/api/v1/employees/'.$this->teamMember->id.'/balance')->assertOk();
        $this->getJson('/api/v1/employees/'.$this->teamMember->id.'/ledger')->assertOk();
        $this->getJson('/api/v1/employees/'.$this->teamMember->id.'/end-of-contract')->assertOk();
        $this->getJson('/api/v1/employees/'.$this->teamMember->id.'/departure/notice')->assertOk();
    }

    public function test_dept_manager_is_forbidden_on_out_of_team_employee(): void
    {
        Sanctum::actingAs($this->deptManager);

        $outside = $this->outsideEmployee;

        // Issue #6545 : un manager dept ne lit PAS les données d'un employé
        // d'un autre département (PA2-SEC-002).
        $this->getJson('/api/v1/employees/'.$outside->id.'/balance')->assertForbidden();
        $this->getJson('/api/v1/employees/'.$outside->id.'/ledger')->assertForbidden();
        $this->getJson('/api/v1/employees/'.$outside->id.'/end-of-contract')->assertForbidden();
        $this->getJson('/api/v1/employees/'.$outside->id.'/certificate-of-employment')->assertForbidden();
        $this->getJson('/api/v1/employees/'.$outside->id.'/departure/notice')->assertForbidden();
    }

    public function test_plain_employee_is_forbidden_on_sensitive_endpoints(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/employees/'.$this->teamMember->id.'/balance')->assertForbidden();
        $this->getJson('/api/v1/employees/'.$this->teamMember->id.'/ledger')->assertForbidden();
    }
}
