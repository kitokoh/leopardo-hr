<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AuthLoginGuardrailsTest extends TestCase
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

    public function test_login_rejects_archived_employee(): void
    {
        $company = Company::query()->create([
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

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'archived@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'archived',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'archived@company.test',
            'password' => 'password123',
            'device_name' => 'tests',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'EMPLOYEE_NOT_ACTIVE');
    }

    public function test_login_rejects_when_company_is_suspended(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'suspended',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'employee@company.test',
            'password' => 'password123',
            'device_name' => 'tests',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'ACCOUNT_SUSPENDED');
    }
}
