<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Marketing\Domain\Models\MarketingLead;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Route;
use Tests\RefreshTenantDatabase;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #5716 — CRM V0 : verrouiller la non-régression du CRM commercial
 * Leopardo (Platform/Marketing).
 *
 * Le CRM commercial (MarketingLead, pipeline `CompanyRequest`, routes
 * `/platform/crm/pipeline` super-admin + `/marketing/*` tenant manager)
 * doit rester strictement séparé du CRM client (tables `crm_*` #5709,
 * futures routes tenant) :
 *   - la route admin pipeline exige `super_admin_api` (jamais un employé) ;
 *   - le payload admin ne contient QUE des données platform (CompanyRequest),
 *     jamais de leads marketing ni de données CRM client ;
 *   - les routes tenant `/marketing/*` ne partagent aucun middleware admin ;
 *   - le RBAC marketing est requis pour les routes leads tenant ;
 *   - l'anti-claim cross-tenant (lead réclamé par une entreprise) reste
 *     effectif (couvert par MarketingLeadConversionTest — 409/404).
 */
class CrmCommercialNonRegressionTest extends TestCase
{
    use CreatesMvpSchema;
    use RefreshTenantDatabase;

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

    public function test_platform_pipeline_route_requires_super_admin(): void
    {
        // Sans authentification : 401.
        $this->getJson('/api/v1/platform/crm/pipeline')->assertUnauthorized();

        // Employé tenant (même avec un rôle) : jamais autorisé sur la route admin.
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/platform/crm/pipeline')->assertUnauthorized();
    }

    public function test_pipeline_payload_contains_only_platform_data(): void
    {
        $this->seedPipelineFixture();

        // Un lead marketing global existe — il ne doit JAMAIS apparaître
        // dans le pipeline admin (qui ne lit que CompanyRequest).
        MarketingLead::query()->create([
            'external_id' => 'lead-global-1',
            'type' => MarketingLead::TYPE_SIGNUP,
            'email' => 'fuite.interdite@exemple.fr',
            'status' => MarketingLead::STATUS_NEW,
        ]);

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

        $response = $this
            ->actingAs($superAdmin, 'super_admin_api')
            ->getJson('/api/v1/platform/crm/pipeline');

        $response->assertOk();

        // Structure du payload : buckets uniquement, aucune donnée tenant.
        $data = $response->json('data');
        $this->assertArrayHasKey('leads', $data);
        $this->assertArrayHasKey('trials', $data);
        $this->assertArrayHasKey('active', $data);
        $this->assertArrayHasKey('rejected', $data);

        $this->assertGreaterThanOrEqual(1, count($data['leads']));

        // Aucun email du MarketingLead global ne fuite dans le payload.
        $payload = $response->json();
        $this->assertStringNotContainsString('fuite.interdite@exemple.fr', json_encode($payload, JSON_THROW_ON_ERROR));

        // Aucune clé de table CRM client (#5709) dans le payload admin.
        foreach (['crm_leads', 'crm_pipelines', 'crm_opportunities', 'email_hmac'] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $data);
            $this->assertStringNotContainsString($forbiddenKey, json_encode($payload, JSON_THROW_ON_ERROR));
        }
    }

    public function test_tenant_marketing_routes_never_use_super_admin_middleware(): void
    {
        $adminMiddleware = 'auth:super_admin_api';

        // Exception légitime : la capture publique de lead vitrine
        // (POST /api/v1/marketing/leads, fail-closed #3888) n'a pas de tenant
        // — elle ingère depuis la vitrine avant tout rattachement entreprise.
        $marketingRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => str_starts_with($route->uri(), 'api/v1/marketing')
                && $route->uri() !== 'api/v1/marketing/leads')
            ->values();

        $this->assertGreaterThan(0, $marketingRoutes->count());

        foreach ($marketingRoutes as $route) {
            $middlewares = $route->middleware();
            $this->assertNotContains(
                $adminMiddleware,
                $middlewares,
                "route tenant {$route->uri()} porte un middleware super-admin"
            );
            // Les routes tenant marketing passent par le middleware tenant.
            $this->assertContains('tenant', $middlewares, "route {$route->uri()} sans middleware tenant");
        }

        // La route admin pipeline vit sous /platform, pas sous /marketing.
        $adminPipeline = collect(Route::getRoutes()->getRoutes())
            ->first(static fn ($route): bool => str_ends_with($route->uri(), 'platform/crm/pipeline'));
        $this->assertNotNull($adminPipeline);
        $this->assertContains($adminMiddleware, $adminPipeline->middleware());
        $this->assertNotContains('tenant', $adminPipeline->middleware());
    }

    public function test_marketing_lead_routes_require_marketing_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $lead = MarketingLead::query()->create([
            'external_id' => 'lead-1',
            'type' => MarketingLead::TYPE_SIGNUP,
            'email' => 'lead@exemple.fr',
            'status' => MarketingLead::STATUS_NEW,
        ]);

        // Employé SANS rôle marketing → 403 sur la conversion/lecture tenant.
        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/marketing/leads/{$lead->id}/qualify")
            ->assertForbidden();

        $this->getJson("/api/v1/marketing/leads/{$lead->id}/contact")
            ->assertForbidden();
    }

    private function seedPipelineFixture(): void
    {
        CompanyRequest::query()->create([
            'company_name' => 'Lead Platform',
            'sector' => 'Services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'lead-platform@exemple.dz',
            'status' => 'pending',
            'signup_payload' => ['source' => 'signup_form'],
        ]);
    }
}
