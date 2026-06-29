<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Laravel\Socialite\Facades\Socialite;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

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
        $response->assertStatus(200);
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

        // 3. Mock Socialite — must chain stateless() before userFromToken() exactly as the
        //    controller does: Socialite::driver('google')->stateless()->userFromToken($token)
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('token-shared@example.com');
        $abstractUser->shouldReceive('getName')->andReturn('Existing User');
        $abstractUser->shouldReceive('offsetGet')->with('given_name')->andReturn('Existing');
        $abstractUser->shouldReceive('offsetGet')->with('family_name')->andReturn('User');

        $provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        // stateless() returns $this so method chaining works
        $provider->shouldReceive('stateless')->once()->andReturn($provider);
        $provider->shouldReceive('userFromToken')->once()->with('fake-token')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        // 4. Call the token endpoint — include device_name to exercise that branch
        $response = $this->postJson('/api/v1/auth/google/token', [
            'access_token' => 'fake-token',
            'device_name'  => 'test-device',
        ]);

        // 5. Assert success
        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $employee->id);

        // No duplicate employee should have been created
        $this->assertEquals(1, Employee::withoutGlobalScopes()->where('email', 'token-shared@example.com')->count());
    }

    public function test_google_login_rejects_unknown_email_with_401()
    {
        // Arrange: no employee exists for the Google email being presented
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('nobody@unknown.example');

        $provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('stateless')->once()->andReturn($provider);
        $provider->shouldReceive('userFromToken')->once()->with('ghost-token')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        // Act: hit the token endpoint with a valid-looking token for an unknown user
        $response = $this->postJson('/api/v1/auth/google/token', [
            'access_token' => 'ghost-token',
            'device_name'  => 'test-device',
        ]);

        // Assert: controller must refuse with 401 — we do NOT create accounts on the
        // token endpoint; the OAuth callback flow handles first-time registration.
        $response->assertStatus(401);
        $response->assertJsonPath('error', 'EMPLOYEE_NOT_FOUND');

        // Verify no employee was silently created
        $this->assertEquals(0, Employee::withoutGlobalScopes()->where('email', 'nobody@unknown.example')->count());
    }
}
