<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Socialite\Facades\Socialite;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;
use Tests\Support\CreatesMvpSchema;

class GoogleAuthGlobalLookupTest extends TestCase
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

    public function test_google_login_finds_employee_even_when_another_tenant_is_bound_to_container()
    {
        // 1. Create Tenant A and an Employee
        $companyA = Company::factory()->create(['name' => 'Company A']);
        $employee = Employee::factory()->create([
            'company_id' => $companyA->id,
            'email' => 'shared@example.com',
            'first_name' => 'Existing',
            'last_name' => 'User',
            'role' => 'employee',
        ]);

        // 2. Create Tenant B and bind it to the container
        $companyB = Company::factory()->create(['name' => 'Company B']);
        app()->instance('current_company', $companyB);

        // 3. Mock Socialite to return the email of the employee in Tenant A
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('shared@example.com');
        $abstractUser->shouldReceive('getName')->andReturn('Existing User');
        $abstractUser->shouldReceive('offsetGet')->with('given_name')->andReturn('Existing');
        $abstractUser->shouldReceive('offsetGet')->with('family_name')->andReturn('User');

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider'));
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        // 4. Call the callback
        $response = $this->getJson('/api/v1/auth/google/callback');

        // 5. Assert success
        // Before the fix, this would fail with a DB error (duplicate email) because Employee::where('email')
        // would only look in Company B due to the BelongsToCompany global scope.
        $response->assertStatus(201);
        $response->assertJsonPath('data.email', 'shared@example.com');
        $response->assertJsonPath('data.id', $employee->id);

        // Verify no new employee was created
        $this->assertEquals(1, Employee::withoutGlobalScopes()->where('email', 'shared@example.com')->count());
    }

    public function test_google_token_login_finds_employee_even_when_another_tenant_is_bound_to_container()
    {
        // 1. Create Tenant A and an Employee
        $companyA = Company::factory()->create(['name' => 'Company A']);
        $employee = Employee::factory()->create([
            'company_id' => $companyA->id,
            'email' => 'token-shared@example.com',
            'role' => 'employee',
        ]);

        // 2. Create Tenant B and bind it to the container
        $companyB = Company::factory()->create(['name' => 'Company B']);
        app()->instance('current_company', $companyB);

        // 3. Mock Socialite
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('token-shared@example.com');
        $abstractUser->shouldReceive('getName')->andReturn('Existing User');
        $abstractUser->shouldReceive('offsetGet')->with('given_name')->andReturn('Existing');
        $abstractUser->shouldReceive('offsetGet')->with('family_name')->andReturn('User');

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider'));
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('userFromToken')->with('fake-token')->andReturn($abstractUser);

        // 4. Call the token endpoint
        $response = $this->postJson('/api/v1/auth/google/token', [
            'token' => 'fake-token',
            'device_name' => 'test-device'
        ]);

        // 5. Assert success
        $response->assertStatus(201);
        $response->assertJsonPath('data.id', $employee->id);

        $this->assertEquals(1, Employee::withoutGlobalScopes()->where('email', 'token-shared@example.com')->count());
    }
}
