<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class DemoUserControllerTest extends TestCase
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

    public function test_demo_users_expose_operational_personas(): void
    {
        $response = $this->getJson('/api/v1/demo-users')
            ->assertOk()
            ->assertJsonPath('data.super_admin.role', 'super_admin')
            ->assertJsonPath('data.companies.0.slug', 'techcorp-algerie');

        $users = collect($response->json('data.companies.0.users'));

        $this->assertTrue($users->contains(fn (array $user): bool => $user['manager_role'] === 'principal'));
        $this->assertTrue($users->contains(fn (array $user): bool => $user['manager_role'] === 'rh'));
        $this->assertTrue($users->contains(fn (array $user): bool => $user['manager_role'] === 'dept'));
        $this->assertTrue($users->contains(fn (array $user): bool => $user['manager_role'] === 'comptable'));
        $this->assertTrue($users->contains(fn (array $user): bool => $user['manager_role'] === 'superviseur'));
        $this->assertTrue($users->contains(fn (array $user): bool => $user['role'] === 'employee'));

        $this->assertSame('kiosk-supervisor', $users->firstWhere('manager_role', 'superviseur')['surface']);
        $this->assertSame('/me', $users->firstWhere('role', 'employee')['primary_path']);
    }

    public function test_demo_users_remain_available_for_public_tester_guides_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');
        config(['app.demo_mode_enabled' => false]);

        $this->getJson('/api/v1/demo-users')
            ->assertOk()
            ->assertJsonPath('data.companies.0.users.0.email', 'ahmed.benali@techcorp-algerie.dz');
    }

    public function test_demo_login_recovers_missing_lookup_from_shared_tenant_schema(): void
    {
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Benali',
            'email' => 'ahmed.benali@techcorp-algerie.dz',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
            'salary_type' => 'fixed',
            'salary_base' => 100000,
            'leave_balance' => 12,
        ]);

        DB::table('public.user_lookups')->where('email', $employee->email)->delete();
        DB::statement('SET search_path TO public');

        $this->postJson('/api/v1/auth/login', [
            'email' => $employee->email,
            'password' => 'password123',
            'device_name' => 'Feature test',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', $employee->email)
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('user_lookups', [
            'email' => $employee->email,
            'company_id' => $company->id,
            'schema_name' => 'shared_tenants',
        ]);
    }
}
