<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AuthenticatedGuardrailsTest extends TestCase
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

    private function createCompany(string $name, string $status = 'active'): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => strtolower(str_replace(' ', '', $name)).'@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => $status,
        ]);
    }

    private function createEmployee(Company $company, string $email, string $status = 'active'): Employee
    {
        return Employee::query()->create([
            'company_id' => $company->id,
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => $status,
        ]);
    }

    public function test_authenticated_employee_blocked_when_archived(): void
    {
        $company = $this->createCompany('Company A');
        $employee = $this->createEmployee($company, 'emp@test.com');

        $this->actingAs($employee, 'sanctum');

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(200);

        $employee->status = 'archived';
        $employee->save();

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'EMPLOYEE_ARCHIVED');
    }

    public function test_authenticated_employee_blocked_when_suspended(): void
    {
        $company = $this->createCompany('Company A');
        $employee = $this->createEmployee($company, 'emp@test.com');

        $this->actingAs($employee, 'sanctum');

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(200);

        $employee->status = 'suspended';
        $employee->save();

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'EMPLOYEE_SUSPENDED');
    }
}
