<?php

namespace Tests\Feature\Security;

use App\Models\AbsenceType;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
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

    public function test_cannot_store_absence_with_other_company_type(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $typeB = AbsenceType::query()->forceCreate([
            'company_id' => $companyB->id,
            'name' => 'Sick B',
            'code' => 'SICK-B',
        ]);

        $employeeA = Employee::query()->forceCreate([
            'company_id' => $companyA->id,
            'email' => 'a@test.com',
            'password_hash' => 'secret',
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employeeA);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $typeB->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-01',
            'reason' => 'Testing cross-tenant',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['absence_type_id']);
    }

    public function test_cannot_store_payroll_for_other_company_employee(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = Employee::query()->forceCreate([
            'company_id' => $companyA->id,
            'email' => 'mgr-a@test.com',
            'password_hash' => 'secret',
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->forceCreate([
            'company_id' => $companyB->id,
            'email' => 'emp-b@test.com',
            'password_hash' => 'secret',
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->postJson('/api/v1/payrolls', [
            'employee_id' => $employeeB->id,
            'period_month' => 1,
            'period_year' => 2026,
            'gross_salary' => 1000,
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
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => strtolower(str_replace(' ', '', $name)).'@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }
}
