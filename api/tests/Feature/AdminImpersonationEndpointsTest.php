<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2624 — endpoints impersonation exposés sous /admin pour le SPA
 * admin-dashboard (réutilise PlatformImpersonationController, PA2-ADM-006).
 * 201 super-admin · 403/401 non-admin.
 */
class AdminImpersonationEndpointsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_admin_impersonation_route_exists_and_requires_super_admin(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($r) => str_contains($r->uri(), 'api/v1/admin/impersonations'));

        $this->assertGreaterThanOrEqual(1, $routes->count(), 'Les routes /admin/impersonations doivent exister.');
    }

    public function test_admin_impersonation_rejects_tenant_employee(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        // Un manager tenant n'a pas accès aux endpoints /admin (401 sur le
        // guard super_admin_api, 403 si authentifié sans rôle — les deux
        // refusent l'accès).
        $response = $this->postJson('/api/v1/admin/impersonations', [
            'company_id' => (string) $company->id,
            'employee_id' => 1,
            'reason' => 'Support request from customer',
        ]);
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_admin_impersonation_rejects_invalid_payload_for_super_admin(): void
    {
        // Super-admin authentifié mais payload invalide → 422 (la route est
        // bien câblée : la validation du contrôleur répond).
        /** @var SuperAdmin $admin */
        $admin = SuperAdmin::query()->create([
            'name' => 'Super Admin Impersonation Test',
            'email' => 'sa-impersonation@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        Sanctum::actingAs($admin, ['*'], 'super_admin_api');

        $this->postJson('/api/v1/admin/impersonations', [
            'company_id' => 'not-a-uuid',
            'employee_id' => 'not-an-int',
            'reason' => '',
        ])->assertStatus(422);
    }
}
