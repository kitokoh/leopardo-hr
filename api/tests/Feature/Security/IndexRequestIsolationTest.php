<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class IndexRequestIsolationTest extends TestCase
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

    public function test_manager_cannot_filter_absences_by_employee_from_another_company(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = Employee::query()->forceCreate([
            'company_id' => $companyA->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->forceCreate([
            'company_id' => $companyB->id,
            'email' => 'employee@b.test',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($managerA);

        // Currently this passes validation but returns 0 results.
        // Sentinel requires it to fail validation.
        $response = $this->getJson('/api/v1/absences?employee_id=' . $employeeB->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
    }

    public function test_manager_cannot_filter_payrolls_by_employee_from_another_company(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = Employee::query()->forceCreate([
            'company_id' => $companyA->id,
            'email' => 'manager-pr@a.test',
            'password_hash' => Hash::make('password'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->forceCreate([
            'company_id' => $companyB->id,
            'email' => 'employee-pr@b.test',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson('/api/v1/payrolls?employee_id=' . $employeeB->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
    }

    public function test_manager_cannot_filter_salary_advances_by_employee_from_another_company(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = Employee::query()->forceCreate([
            'company_id' => $companyA->id,
            'email' => 'manager-sa@a.test',
            'password_hash' => Hash::make('password'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->forceCreate([
            'company_id' => $companyB->id,
            'email' => 'employee-sa@b.test',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson('/api/v1/salary-advances?employee_id=' . $employeeB->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
    }

    public function test_manager_cannot_filter_attendances_by_employee_from_another_company(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = Employee::query()->forceCreate([
            'company_id' => $companyA->id,
            'email' => 'manager-at@a.test',
            'password_hash' => Hash::make('password'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->forceCreate([
            'company_id' => $companyB->id,
            'email' => 'employee-at@b.test',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson('/api/v1/attendance?employee_id=' . $employeeB->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
    }

    public function test_manager_cannot_filter_evaluations_by_employee_from_another_company(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $managerA = Employee::query()->forceCreate([
            'company_id' => $companyA->id,
            'email' => 'manager-ev@a.test',
            'password_hash' => Hash::make('password'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->forceCreate([
            'company_id' => $companyB->id,
            'email' => 'employee-ev@b.test',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson('/api/v1/evaluations?employee_id=' . $employeeB->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employee_id']);
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => $name,
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $name . '@test.com',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);
    }
}
