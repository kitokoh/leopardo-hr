<?php

namespace Tests\Feature\Security;

use App\Models\AbsenceType;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class CrossTenantValidationTest extends TestCase
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

    public function test_manager_cannot_create_absence_with_another_tenant_absence_type(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $absenceTypeB = AbsenceType::create([
            'company_id' => $companyB->id,
            'name' => 'Boutique Leave',
            'code' => 'BL',
            'deducts_leave' => true,
        ]);

        $response = $this->actingAs($managerA, 'sanctum')
            ->postJson('/api/v1/absences', [
                'absence_type_id' => $absenceTypeB->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'reason' => 'Stealing some leave',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['absence_type_id']);
    }

    public function test_manager_cannot_create_payroll_for_another_tenant_employee(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = $this->createEmployee($companyA, 'manager', 'principal');
        $employeeB = $this->createEmployee($companyB, 'employee');

        $response = $this->actingAs($managerA, 'sanctum')
            ->postJson('/api/v1/payrolls', [
                'employee_id' => $employeeB->id,
                'period_month' => 5,
                'period_year' => 2026,
                'gross_salary' => 3000,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
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
        return Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'email' => strtolower(Str::random(10)).'@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
    }
}
