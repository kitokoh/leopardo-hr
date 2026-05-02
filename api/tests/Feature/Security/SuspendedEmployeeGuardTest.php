<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class SuspendedEmployeeGuardTest extends TestCase
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

    /**
     * Test that a suspended employee is blocked from accessing the API.
     */
    public function test_suspended_employee_is_blocked_by_tenant_middleware(): void
    {
        $company = Company::query()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'suspended@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'suspended',
        ]);

        $plain = $employee->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'EMPLOYEE_SUSPENDED');
    }

    /**
     * Test that a login attempt for a suspended employee is rejected.
     */
    public function test_login_rejects_suspended_employee(): void
    {
        $company = Company::query()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a2@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'suspended-login@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'suspended',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended-login@company.test',
            'password' => 'password123',
            'device_name' => 'tests',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'EMPLOYEE_NOT_ACTIVE');
    }
}
