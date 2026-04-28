<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Language;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AuthMeLogoutTest extends TestCase
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

    public function test_me_returns_authenticated_employee(): void
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

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $employee->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');
        $response->assertOk();
        $response->assertJsonPath('data.email', 'employee@company.test');
    }

    public function test_me_returns_language_and_rtl_metadata(): void
    {
        Language::query()->create([
            'code' => 'fr',
            'name_fr' => 'Français',
            'name_native' => 'Français',
            'is_rtl' => false,
            'is_active' => true,
        ]);

        Language::query()->create([
            'code' => 'ar',
            'name_fr' => 'Arabe',
            'name_native' => 'العربية',
            'is_rtl' => true,
            'is_active' => true,
        ]);

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
            'language' => 'fr',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'preferred_language' => 'ar',
        ]);

        $token = $employee->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonPath('data.language', 'ar');
        $response->assertJsonPath('data.is_rtl', true);
    }

    public function test_logout_revokes_current_token(): void
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

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $plain = $employee->createToken('tests')->plainTextToken;
        $this->assertSame(1, $employee->tokens()->count());

        $response = $this->withHeader('Authorization', "Bearer {$plain}")
            ->postJson('/api/v1/auth/logout');
        $response->assertOk();

        $this->assertSame(0, $employee->tokens()->count());
    }

    public function test_archived_employee_token_is_blocked_by_tenant_middleware(): void
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

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'archived@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'archived',
        ]);

        $plain = $employee->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'EMPLOYEE_ARCHIVED');
    }

    public function test_company_suspension_revokes_employee_tokens(): void
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

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $employee->createToken('tests');
        $this->assertSame(1, $employee->tokens()->count());

        $company->status = 'suspended';
        $company->save();

        $this->assertSame(0, $employee->fresh()->tokens()->count());
    }
}
