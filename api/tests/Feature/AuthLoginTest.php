<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AuthLoginTest extends TestCase
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

    public function test_login_returns_token(): void
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
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@company.test',
            'password' => 'password123',
            'device_name' => 'tests',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'manager@company.test');
        $response->assertJsonPath('data.role', 'manager');
        $response->assertJsonPath('data.language', 'fr');
        $response->assertJsonPath('data.capabilities.can_view_dashboard', true);
        $response->assertJsonStructure(['token']);
        $response->assertJsonPath('token_type', 'Bearer');
        $this->assertNotNull($response->json('token_expires_at'));

        $employee = Employee::query()->where('email', 'manager@company.test')->firstOrFail();
        $this->assertNotNull($employee->last_login_at);
    }

    public function test_login_token_opens_client_session_profile(): void
    {
        $company = Company::query()->create([
            'name' => 'Company Session',
            'slug' => 'company-session',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'session@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'language' => 'ar',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'rh@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
            'preferred_language' => 'fr',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'rh@company.test',
            'password' => 'password123',
            'device_name' => 'web-client-contract',
        ])->assertOk();

        $token = $login->json('token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'rh@company.test')
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('data.manager_role', 'rh')
            ->assertJsonPath('data.language', 'fr')
            ->assertJsonPath('data.is_rtl', false)
            ->assertJsonPath('data.capabilities.can_create_employees', true)
            ->assertJsonPath('data.company.name', 'Company Session')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'company_id',
                    'first_name',
                    'last_name',
                    'email',
                    'role',
                    'manager_role',
                    'language',
                    'is_rtl',
                    'capabilities',
                    'features',
                    'mobile_experience',
                    'suggested_home_route',
                    'company' => [
                        'id',
                        'name',
                        'language',
                        'timezone',
                        'currency',
                    ],
                ],
            ]);
    }

    public function test_login_rejects_invalid_credentials(): void
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
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@company.test',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'INVALID_CREDENTIALS');
        $this->assertNotNull($response->json('localized_message'));
    }
}
