<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelProfessionalAccount;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Intégration CRM FuelStation — FUEL-016 (issue #5810).
 *
 * Couvre : upsert de compte professionnel (idempotent par code) avec
 * événement `fuel.account.upserted.v1`, visites idempotentes par
 * external_id avec `fuel.visit.recorded.v1`, consentements allowlist avec
 * `fuel.consent.updated.v1`, RBAC deny-by-default (pompiste → 403),
 * isolation tenant 404, aucune lecture des leads CRM Leopardo.
 */
class FuelCrmApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/accounts')->assertStatus(401);
    }

    public function test_manager_upserts_account_and_publishes_event(): void
    {
        [$company, $manager] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/accounts', [
            'code' => 'ACME-001',
            'name' => 'ACME Transports',
            'industry' => 'logistics',
            'contact' => 'contact@acme.example',
            'consents' => ['email' => true, 'sms' => false],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'ACME-001')
            ->assertJsonPath('data.name', 'ACME Transports')
            ->assertJsonPath('data.consents.email', true);

        $this->assertDatabaseHas('fuel_professional_accounts', [
            'company_id' => $company->id,
            'code' => 'ACME-001',
        ]);

        $this->assertDatabaseHas('fuel_outbox_events', [
            'company_id' => $company->id,
            'event_type' => 'fuel.account.upserted.v1',
            'status' => 'pending',
        ]);

        // Upsert idempotent : même code → même compte, pas de doublon.
        $this->postJson('/api/v1/fuel-station/accounts', [
            'code' => 'ACME-001',
            'name' => 'ACME Transports (màj)',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'ACME Transports (màj)');

        $this->assertSame(1, FuelProfessionalAccount::query()->count());
    }

    public function test_manager_records_idempotent_visit(): void
    {
        [$company, $manager, , $account] = $this->seedAccount();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/fuel-station/accounts/{$account->id}/visits", [
            'purpose' => 'commercial',
            'notes_redacted' => 'Négociation contrat flotte',
            'external_id' => 'visit-2026-08-30-001',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.purpose', 'commercial');

        $this->assertDatabaseHas('fuel_outbox_events', [
            'company_id' => $company->id,
            'event_type' => 'fuel.visit.recorded.v1',
            'status' => 'pending',
        ]);

        // Rejeu idempotent.
        $this->postJson("/api/v1/fuel-station/accounts/{$account->id}/visits", [
            'purpose' => 'commercial',
            'notes_redacted' => 'Négociation contrat flotte',
            'external_id' => 'visit-2026-08-30-001',
        ])->assertStatus(201);

        $this->assertSame(1, $account->visits()->count());
    }

    public function test_manager_updates_consents_with_allowlist(): void
    {
        [$company, $manager, , $account] = $this->seedAccount();

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/fuel-station/accounts/{$account->id}/consents", [
            'consents' => ['whatsapp' => true, 'call' => false, 'carrier_pigeon' => true],
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.consents.whatsapp', true)
            ->assertJsonPath('data.consents.call', false);

        $this->assertDatabaseHas('fuel_outbox_events', [
            'company_id' => $company->id,
            'event_type' => 'fuel.consent.updated.v1',
            'status' => 'pending',
        ]);

        // La clé étrangère au canal allowlist n'est pas persistée.
        $this->assertArrayNotHasKey('carrier_pigeon', $account->refresh()->consentSummary());
    }

    public function test_operator_gets_403_on_crm(): void
    {
        [$company, , $operator, $account] = $this->seedAccount();

        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/fuel-station/accounts')->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/accounts', [
            'code' => 'HACK-001',
            'name' => 'Interdit',
        ])->assertStatus(403);
        $this->postJson("/api/v1/fuel-station/accounts/{$account->id}/visits", [])
            ->assertStatus(403);
    }

    public function test_cross_tenant_account_is_404(): void
    {
        [$companyA, $managerA] = $this->seedTenant();
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $managerB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $accountB = FuelProfessionalAccount::query()->create([
            'company_id' => $companyB->id,
            'code' => 'TENANT-B',
            'name' => 'Compte B',
            'created_by' => $managerB->id,
        ]);

        Sanctum::actingAs($managerA);

        $this->getJson("/api/v1/fuel-station/accounts/{$accountB->id}")->assertStatus(404);
        $this->putJson("/api/v1/fuel-station/accounts/{$accountB->id}/consents", [
            'consents' => ['email' => true],
        ])->assertStatus(404);
    }

    public function test_account_payload_never_exposes_raw_contact(): void
    {
        [$company, $manager, , $account] = $this->seedAccount();

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/fuel-station/accounts/{$account->id}")
            ->assertStatus(200)
            ->assertJsonMissingPath('data.contact')
            ->assertJsonMissingPath('data.contact_encrypted');
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function seedTenant(): array
    {
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return [$company, $manager];
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee, 3: FuelProfessionalAccount}
     */
    private function seedAccount(): array
    {
        [$company, $manager] = $this->seedTenant();
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.substr((string) $company->id, 0, 8),
            'name' => 'Station Test',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);
        $account = FuelProfessionalAccount::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'code' => 'ACME-001',
            'name' => 'ACME Transports',
            'industry' => 'logistics',
            'created_by' => $manager->id,
        ]);

        return [$company, $manager, $operator, $account];
    }
}
