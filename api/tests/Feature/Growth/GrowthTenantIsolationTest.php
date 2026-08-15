<?php

namespace Tests\Feature\Growth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Audit expert 2026-08-15 (issue #2622) : le groupe /growth/partner/* est
 * désormais soumis au middleware `tenant` (contexte tenant + guards statut
 * entreprise/employé). Les partenaires restent globaux par design
 * (public.partners keyed by user) mais la requête exige un tenant actif.
 */
class GrowthTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_partner_apply_requires_tenant_context(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['schema_name' => 'shared_tenants']);
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'status' => 'active',
            'email' => 'growth.manager@example.com',
        ]);

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/growth/partner/apply', [
            'type' => 'individual',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('partners', [
            'type' => 'individual',
        ]);
    }

    public function test_partner_apply_blocked_for_suspended_company(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'status' => 'suspended',
        ]);
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($manager, 'sanctum')->postJson('/api/v1/growth/partner/apply', [
            'type' => 'individual',
        ])->assertStatus(403);
    }

    public function test_partner_apply_rejects_non_ordinary_without_tenant(): void
    {
        // Un manager sans entreprise est un état cassé → le middleware tenant
        // refuse (403 COMPANY_NOT_FOUND). Les comptes ordinary pré-tenant
        // (parcours company-request) passent par design.
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => null,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($employee, 'sanctum')->postJson('/api/v1/growth/partner/apply', [
            'type' => 'individual',
        ])->assertStatus(403);
    }
}
