<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Database\Seeders\DemoCompanyOnceSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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

    public function test_demo_once_seeder_keeps_public_super_admin_credentials_usable(): void
    {
        config(['app.demo_mode_enabled' => true]);

        DB::table('public.super_admins')->insert([
            'name' => 'Super Administrateur',
            'email' => 'admin@leopardo-rh.com',
            'password_hash' => Hash::make('old-random-password'),
            'two_fa_secret' => 'ABCDEFGHIJKLMNOP',
            'created_at' => now(),
        ]);

        Schema::create('seed_locks', function (Blueprint $table): void {
            $table->string('lock_key')->primary();
            $table->timestampTz('ran_at')->nullable();
            $table->timestampsTz();
        });

        foreach (['techcorp-algerie', 'pharmaplus-casablanca', 'digitalflow-tunis'] as $slug) {
            Company::factory()->create([
                'slug' => $slug,
                'schema_name' => 'shared_tenants',
                'tenancy_type' => 'shared',
                'status' => 'active',
            ]);
        }

        $this->seed(DemoCompanyOnceSeeder::class);

        $superAdmin = DB::table('public.super_admins')
            ->where('email', 'admin@leopardo-rh.com')
            ->first();

        $this->assertNotNull($superAdmin);
        $this->assertTrue(Hash::check('password123', $superAdmin->password_hash));
        $this->assertNull($superAdmin->two_fa_secret);
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

        DB::statement('CREATE TABLE IF NOT EXISTS shared_tenants.companies (LIKE public.companies INCLUDING ALL)');
        DB::table('public.user_lookups')->where('email', $employee->email)->delete();
        DB::statement('SET search_path TO public');

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $employee->email,
            'password' => 'password123',
            'device_name' => 'Feature test',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', $employee->email)
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonStructure(['token']);

        $this->withToken($loginResponse->json('token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $employee->email)
            ->assertJsonPath('data.company.id', $company->id);

        $this->assertDatabaseHas('user_lookups', [
            'email' => $employee->email,
            'company_id' => $company->id,
            'schema_name' => 'shared_tenants',
        ]);
    }
}
