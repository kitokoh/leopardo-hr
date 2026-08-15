<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2622 — le groupe growth/partner doit porter le middleware `tenant`
 * (isolation : le search_path tenant est posé avant toute requête métier).
 */
class GrowthPartnerTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
    }

    public function test_growth_partner_routes_carry_tenant_middleware(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/growth/partner/dashboard' && in_array('GET', $r->methods(), true));

        $this->assertNotNull($route, 'Route GET /api/v1/growth/partner/dashboard introuvable.');
        $this->assertContains('tenant', $route->gatherMiddleware(), 'Le middleware tenant doit être présent sur le groupe growth/partner.');
    }

    public function test_growth_partner_dashboard_accessible_with_tenant_context(): void
    {
        Sanctum::actingAs($this->manager);

        // Avec le middleware tenant, la requête pose le search_path du tenant
        // et répond (200 ou 404 métier selon les données) — jamais 500.
        $response = $this->getJson('/api/v1/growth/partner/dashboard');
        $this->assertNotEquals(500, $response->getStatusCode());
    }
}
