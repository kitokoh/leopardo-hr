<?php

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Audit expert 2026-08-15 (issue #2624) : la SPA admin appelle
 * POST /admin/impersonations (UsersView.vue:435) — la route n'existait pas
 * (404) ; seul /platform/impersonations existait. Vérifie l'alias /admin sur
 * le même contrôleur (raison obligatoire, session temps limité).
 */
class AdminImpersonationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create(['schema_name' => 'shared_tenants']);
        $this->assertInstanceOf(Company::class, $company);
        $this->company = $company;
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'status' => 'active',
        ]);
        $this->assertInstanceOf(Employee::class, $employee);
        $this->employee = $employee;

        $this->admin = SuperAdmin::create([
            'name' => 'Admin',
            'email' => 'admin@leopardo.test',
            'password_hash' => Hash::make('secret'),
        ]);
    }

    public function test_admin_impersonation_requires_super_admin_auth(): void
    {
        $this->postJson('/api/v1/admin/impersonations', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'reason' => 'Support client',
        ])->assertUnauthorized();
    }

    public function test_admin_impersonation_starts_session(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'super_admin_api');

        $response = $this->postJson('/api/v1/admin/impersonations', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'reason' => 'Support client',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data', 'token', 'token_type', 'expires_at']);
    }

    public function test_admin_impersonation_unknown_company_is_404(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'super_admin_api');

        $this->postJson('/api/v1/admin/impersonations', [
            'company_id' => '00000000-0000-0000-0000-000000000001',
            'employee_id' => $this->employee->id,
            'reason' => 'Support client',
        ])->assertStatus(404);
    }
}
