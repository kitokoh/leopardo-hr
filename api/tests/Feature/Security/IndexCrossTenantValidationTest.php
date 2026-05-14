<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class IndexCrossTenantValidationTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_salary_advance_index_rejects_cross_tenant_employee_id(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson('/api/v1/salary-advances?employee_id='.$employeeB->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
        $response->assertJsonPath('errors.employee_id.0', "Employ\u{00E9} introuvable dans votre entreprise.");
    }

    public function test_absence_index_rejects_cross_tenant_employee_id(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson('/api/v1/absences?employee_id='.$employeeB->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
        $response->assertJsonPath('errors.employee_id.0', "Employ\u{00E9} introuvable dans votre entreprise.");
    }

    public function test_evaluation_index_rejects_cross_tenant_employee_id(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson('/api/v1/evaluations?employee_id='.$employeeB->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
        $response->assertJsonPath('errors.employee_id.0', "Employ\u{00E9} introuvable dans votre entreprise.");
    }

    public function test_evaluation_index_rejects_cross_tenant_evaluator_id(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $managerB = $this->createEmployee($companyB, 'manager', 'principal');

        $response = $this->actingAs($managerA, 'sanctum')
            ->getJson('/api/v1/evaluations?evaluator_id='.$managerB->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['evaluator_id']);
        $response->assertJsonPath('errors.evaluator_id.0', "\u{00C9}valuateur introuvable dans votre entreprise.");
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sector' => 'test',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => strtolower(Str::random(8)).'@test.com',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }

    private function createEmployee(Company $company, string $role, ?string $managerRole = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'email' => strtolower(Str::random(10)).'@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);

        return $employee;
    }
}
