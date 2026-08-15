<?php

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #2652 — le login ne doit jamais répondre 500 quand le lookup pointe vers un
 * schéma tenant absent ou partiellement migré (ex. comptes démo en production
 * avec DISABLE_DEMO_SEEDING). La résolution d'employé échoue proprement → 401.
 */
class AuthLoginDefensiveTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_login_returns_401_instead_of_500_when_lookup_points_to_missing_tenant_schema(): void
    {
        $company = Company::factory()->create([
            'schema_name' => 'ghost_tenant_schema',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        DB::table('public.user_lookups')->insert([
            'email' => 'ghost@missing-schema.dz',
            'company_id' => $company->id,
            'schema_name' => 'ghost_tenant_schema',
            'employee_id' => 424242,
            'role' => 'employee',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@missing-schema.dz',
            'password' => 'password123',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error', 'INVALID_CREDENTIALS');
    }

    public function test_login_returns_401_when_lookup_schema_exists_but_employee_row_is_missing(): void
    {
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        DB::table('public.user_lookups')->insert([
            'email' => 'orphan@lookup.dz',
            'company_id' => $company->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => 999999,
            'role' => 'employee',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'orphan@lookup.dz',
            'password' => 'password123',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error', 'INVALID_CREDENTIALS');
    }

    public function test_login_nominal_path_is_unchanged(): void
    {
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'nominal@tenant.dz',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
            'salary_type' => 'fixed',
            'salary_base' => 100000,
        ]);

        DB::table('public.user_lookups')->updateOrInsert(
            ['email' => $employee->email],
            [
                'company_id' => $company->id,
                'schema_name' => 'shared_tenants',
                'employee_id' => $employee->id,
                'role' => 'manager',
            ]
        );

        $this->postJson('/api/v1/auth/login', [
            'email' => $employee->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'data' => ['email']])
            ->assertJsonPath('data.email', $employee->email);
    }
}
