<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelStationActivation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * FUEL-001 — Manifest de solution, catalogue et activation tenant (issue #5795).
 *
 * Manifest validé par allowlist, activation idempotente, dépendances
 * manquantes refusées, CRM commercial hors périmètre.
 */
class FuelStationManifestTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(string $managerRole = 'principal'): \App\Core\Auth\Domain\Models\Employee
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $manager */
        $manager = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function ordinaryEmployee(): \App\Core\Auth\Domain\Models\Employee
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    public function test_manifest_is_served_with_validated_fields(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/v1/fuelstation/manifest')
            ->assertOk()
            ->assertJsonPath('data.manifest.key', 'fuelstation')
            ->assertJsonPath('data.manifest.maturity', 'pilot')
            ->assertJsonPath('data.activated', false)
            ->assertJsonPath('data.manifest.dependencies.hr', true);
    }

    public function test_employee_cannot_access_fuelstation(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee());

        $this->getJson('/api/v1/fuelstation/manifest')->assertStatus(403);
    }

    public function test_activation_requires_base_modules(): void
    {
        Sanctum::actingAs($this->manager());

        // Aucune dépendance activée sur la company (rh=true par défaut,
        // attendance/payroll absents) → activation refusée 422 avec liste.
        $this->postJson('/api/v1/fuelstation/activate')
            ->assertStatus(422)
            ->assertJsonPath('error', 'FUEL_DEPENDENCIES_MISSING');

        $this->assertDatabaseMissing('fuel_station_activations', [
            'company_id' => $this->company->id,
        ]);
    }

    public function test_activation_is_idempotent_and_sets_feature_flag(): void
    {
        Sanctum::actingAs($this->manager());

        $this->company->setFeature('attendance', true);
        $this->company->setFeature('payroll', true);
        $this->company->save();

        $this->postJson('/api/v1/fuelstation/activate')
            ->assertOk()
            ->assertJsonPath('data.key', 'fuelstation')
            ->assertJsonPath('data.status', 'active');

        // Feature flag posé + activation persistée.
        $this->assertTrue($this->company->refresh()->hasFeature('fuelstation'));
        $this->assertDatabaseHas('fuel_station_activations', [
            'company_id' => $this->company->id,
            'manifest_version' => '1.0.0',
            'status' => 'active',
        ]);

        // Rejouer l'activation → idempotent (toujours une seule ligne).
        $this->postJson('/api/v1/fuelstation/activate')->assertOk();
        $this->assertSame(1, FuelStationActivation::query()->where('company_id', $this->company->id)->count());
    }

    public function test_status_reflects_activation(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/v1/fuelstation/status')->assertJsonPath('data.activated', false);

        FuelStationActivation::query()->create([
            'company_id' => $this->company->id,
            'manifest_version' => '1.0.0',
            'status' => 'active',
            'activated_at' => now(),
        ]);

        $this->getJson('/api/v1/fuelstation/status')
            ->assertJsonPath('data.activated', true)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_other_tenant_activation_is_not_visible(): void
    {
        Sanctum::actingAs($this->manager());

        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        FuelStationActivation::query()->create([
            'company_id' => $other->id,
            'manifest_version' => '1.0.0',
            'status' => 'active',
            'activated_at' => now(),
        ]);

        $this->getJson('/api/v1/fuelstation/status')
            ->assertOk()
            ->assertJsonPath('data.activated', false);
    }
}
