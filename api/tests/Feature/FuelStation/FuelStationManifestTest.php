<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Manifest\FuelStationManifest;
use App\Modules\FuelStation\Infrastructure\Services\FuelStationActivationService;
use App\Modules\FuelStation\Infrastructure\Services\FuelStationFeatureRegistrar;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Manifest, catalogue et feature flag FuelStation — Issue #5795 (FUEL-001).
 *
 * Verrouille :
 *   1. manifest validé par allowlist (validKey) ;
 *   2. catalogue enregistré dans le Feature Registry (features) de façon
 *      idempotente ;
 *   3. activation tenant idempotente + dépendances manquantes refusées ;
 *   4. feature flag `fuel_station` consultable (FeatureFlag::enabled) ;
 *   5. RBAC : manifest en lecture managers principal/rh, activation réservée
 *      principal ; employé ordinaire 403 ;
 *   6. le CRM commercial plateforme n'est jamais modifié.
 */
class FuelStationManifestTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    // ── Manifest allowlisté ───────────────────────────────────────────────

    public function test_manifest_allowlist_accepts_catalogue_keys_only(): void
    {
        $this->assertTrue(FuelStationManifest::validKey('fuel_station'));
        $this->assertTrue(FuelStationManifest::validKey('fuel_station.manifest'));
        $this->assertTrue(FuelStationManifest::validKey('fuel_station.readings'));
        $this->assertFalse(FuelStationManifest::validKey('fuel_station.evil'));
        $this->assertFalse(FuelStationManifest::validKey('platform_crm_takeover'));
        $this->assertFalse(FuelStationManifest::validKey(''));
    }

    // ── Catalogue enregistré (idempotent) ─────────────────────────────────

    public function test_catalogue_registration_is_idempotent(): void
    {
        $registrar = app(FuelStationFeatureRegistrar::class);

        $first = $registrar->registerAll();
        $second = $registrar->registerAll();

        $this->assertSame(count(FuelStationManifest::FEATURES), $first);
        $this->assertSame($first, $second);
    }

    // ── Activation idempotente + dépendances ──────────────────────────────

    public function test_activation_is_idempotent(): void
    {
        $service = app(FuelStationActivationService::class);

        $firstTime = $service->activate($this->companyA);
        $secondTime = $service->activate($this->companyA);

        $this->assertTrue($firstTime);
        $this->assertFalse($secondTime);
        $this->assertTrue($service->isActive($this->companyA->fresh()));
    }

    public function test_activation_refused_when_required_dependency_disabled(): void
    {
        $company = $this->companyA;
        $company->setFeature('attendance', false);

        $service = app(FuelStationActivationService::class);

        $this->expectException(\App\Modules\FuelStation\Infrastructure\Services\FuelStationActivationException::class);

        $service->activate($company);
    }

    public function test_activation_refused_for_inactive_tenant(): void
    {
        $company = $this->companyB;
        $company->forceFill(['status' => 'suspended'])->save();

        $service = app(FuelStationActivationService::class);

        $this->expectException(\App\Modules\FuelStation\Infrastructure\Services\FuelStationActivationException::class);

        $service->activate($company->fresh());
    }

    // ── API ───────────────────────────────────────────────────────────────

    public function test_manifest_route_requires_authentication(): void
    {
        $this->getJson('/api/v1/fuel-station/manifest')->assertStatus(401);
        $this->postJson('/api/v1/fuel-station/activate')->assertStatus(401);
    }

    public function test_manifest_returns_solution_contract(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/fuel-station/manifest');

        $response->assertOk();
        $response->assertJsonPath('data.key', 'fuel_station');
        $response->assertJsonPath('data.maturity', 'pilot');
        $response->assertJsonPath('data.active', false);
        $response->assertJsonPath('data.requires.0', 'rh');
        $this->assertArrayHasKey('features', $response->json('data'));
    }

    public function test_activate_via_api_is_idempotent(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'principal'));

        $first = $this->postJson('/api/v1/fuel-station/activate');
        $first->assertStatus(201);
        $first->assertJsonPath('data.active', true);
        $first->assertJsonPath('data.activated', true);

        $second = $this->postJson('/api/v1/fuel-station/activate');
        $second->assertStatus(200);
        $second->assertJsonPath('data.activated', false);
    }

    public function test_activate_refused_when_dependency_disabled_via_api(): void
    {
        $this->companyA->setFeature('attendance', false);

        Sanctum::actingAs($this->manager($this->companyA, 'principal'));

        $response = $this->postJson('/api/v1/fuel-station/activate');

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'FUEL_STATION_ACTIVATION_REFUSED');
    }

    public function test_activate_is_principal_only(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'rh'));

        $this->postJson('/api/v1/fuel-station/activate')->assertStatus(403);
    }

    public function test_employee_cannot_access_manifest(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $this->getJson('/api/v1/fuel-station/manifest')->assertStatus(403);
    }

    // ── Feature flag + non-régression CRM ─────────────────────────────────

    public function test_feature_flag_enabled_after_activation(): void
    {
        $service = app(FuelStationActivationService::class);
        $service->activate($this->companyA);

        $this->assertTrue(\App\Core\Feature\Infrastructure\Services\FeatureFlag::enabled('fuel_station', $this->companyA->fresh()));
        $this->assertFalse(\App\Core\Feature\Infrastructure\Services\FeatureFlag::enabled('fuel_station', $this->companyB));
    }

    public function test_commercial_crm_is_never_touched(): void
    {
        $service = app(FuelStationActivationService::class);
        $service->activate($this->companyA);

        // Aucune écriture sur les surfaces CRM commercial (Platform/Marketing).
        $this->assertSame(0, \App\Modules\Marketing\Domain\Models\MarketingLead::query()->count());
        $this->assertFalse($this->companyA->fresh()->hasFeature('marketing_lead'));
    }
}
