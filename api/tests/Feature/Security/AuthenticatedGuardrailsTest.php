<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
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

    public function test_authenticated_employee_is_blocked_immediately_if_archived(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        // First request should be OK
        $this->getJson('/api/v1/auth/me')->assertOk();

        // Simulate status change to archived
        $employee->status = 'archived';
        $employee->save();

        // Next request should be blocked
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(403);
        $response->assertJsonPath('error', 'EMPLOYEE_ARCHIVED');
    }

    public function test_authenticated_employee_is_blocked_immediately_if_suspended(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        // First request should be OK
        $this->getJson('/api/v1/auth/me')->assertOk();

        // Simulate status change to suspended
        $employee->status = 'suspended';
        $employee->save();

        // Next request should be blocked
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(403);
        $response->assertJsonPath('error', 'EMPLOYEE_SUSPENDED');
    }

    public function test_authenticated_employee_is_blocked_immediately_if_company_is_suspended(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        // First request should be OK
        $this->getJson('/api/v1/auth/me')->assertOk();

        // Simulate company status change to suspended
        $company->status = 'suspended';
        $company->save();

        // Unset relationship to force middleware to reload it from DB
        $employee->unsetRelation('company');

        // Next request should be blocked
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(403);
        $response->assertJsonPath('error', 'ACCOUNT_SUSPENDED');
    }

    public function test_authenticated_employee_is_blocked_immediately_if_company_is_expired(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        // First request should be OK
        $this->getJson('/api/v1/auth/me')->assertOk();

        // Simulate company status change to expired
        $company->status = 'expired';
        $company->save();

        // Unset relationship to force middleware to reload it from DB
        $employee->unsetRelation('company');

        // Next request should be blocked
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(403);
        $response->assertJsonPath('error', 'ACCOUNT_SUSPENDED');
    }
}
