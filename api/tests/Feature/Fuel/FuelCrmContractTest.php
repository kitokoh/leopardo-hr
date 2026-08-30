<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelCustomer;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Intégration CRM client FuelStation — FUEL-016 (issue #5810).
 *
 * Couvre : CRM tenant uniquement (jamais les leads Leopardo), consentement
 * marketing explicite horodaté (opt-in/opt-out), crédit de fidélité UNIQUE
 * par visite (idempotency_key), rejeu idempotent par external_id, RBAC
 * manager, 404 sûr cross-tenant.
 */
class FuelCrmContractTest extends TestCase
{
    use RefreshTenantDatabase;

    private function setupCompany(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-CRM',
            'name' => 'CRM',
            'timezone' => 'UTC',
        ]);

        return [$company, $manager, $operator, $station];
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/customers')->assertStatus(401);
    }

    public function test_operator_cannot_access_customers(): void
    {
        [$company, , $operator, $station] = $this->setupCompany();
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/fuel-station/customers')->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/customers', [
            'name' => 'Sté Transports A',
        ])->assertStatus(403);
    }

    public function test_manager_registers_customer_with_explicit_consent(): void
    {
        [$company, $manager, , $station] = $this->setupCompany();
        Sanctum::actingAs($manager);

        $customerId = $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/customers', [
            'name' => 'Sté Transports A',
            'contact_email' => 'contact@transports-a.example',
            'marketing_consent' => true,
            'external_id' => 'CRM-EXT-001',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.marketing_consent', true)
            ->assertJsonPath('data.loyalty_points', 0)
            ->json('data.id');

        $this->assertNotNull(
            FuelCustomer::query()->find($customerId)?->opted_in_at
        );

        // Rejeu avec le même external_id → idempotent (même client).
        $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/customers', [
            'name' => 'Sté Transports A',
            'marketing_consent' => true,
            'external_id' => 'CRM-EXT-001',
        ])->assertStatus(201);

        $this->getJson('/api/v1/fuel-station/customers')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_consent_opt_out_is_timestamped(): void
    {
        [$company, $manager, , $station] = $this->setupCompany();
        Sanctum::actingAs($manager);

        $customerId = $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/customers', [
            'name' => 'Sté B',
            'marketing_consent' => true,
        ])->json('data.id');

        $this->patchJson('/api/v1/fuel-station/customers/'.$customerId.'/consent', [
            'marketing_consent' => false,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.marketing_consent', false);

        $customer = FuelCustomer::query()->find($customerId);
        $this->assertNotNull($customer?->opted_out_at);
        $this->assertNotNull($customer?->opted_in_at);
    }

    public function test_visit_credits_loyalty_once(): void
    {
        [$company, $manager, , $station] = $this->setupCompany();
        Sanctum::actingAs($manager);

        $customerId = $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/customers', [
            'name' => 'Sté C',
        ])->json('data.id');

        $this->postJson('/api/v1/fuel-station/customers/'.$customerId.'/visits', [
            'station_id' => $station->id,
            'notes' => 'Plein de 200 L',
            'idempotency_key' => 'visit-2026-08-30-001',
        ])->assertStatus(201);

        // Rejeu → aucune visite dupliquée, aucun point en double.
        $this->postJson('/api/v1/fuel-station/customers/'.$customerId.'/visits', [
            'station_id' => $station->id,
            'notes' => 'Plein de 200 L',
            'idempotency_key' => 'visit-2026-08-30-001',
        ])->assertStatus(201);

        $this->getJson('/api/v1/fuel-station/customers/'.$customerId)
            ->assertStatus(200)
            ->assertJsonPath('data.loyalty_points', 1);

        $this->getJson('/api/v1/fuel-station/customers/'.$customerId.'/visits')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_cross_tenant_customer_is_404(): void
    {
        [$companyA, , , $stationA] = $this->setupCompany();
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        /** @var FuelCustomer $customerA */
        $customerA = FuelCustomer::query()->create([
            'company_id' => $companyA->id,
            'station_id' => $stationA->id,
            'name' => 'Client A',
        ]);

        Sanctum::actingAs($managerB);
        $this->getJson('/api/v1/fuel-station/customers/'.$customerA->id)->assertStatus(404);
        $this->patchJson('/api/v1/fuel-station/customers/'.$customerA->id.'/consent', [
            'marketing_consent' => false,
        ])->assertStatus(404);
    }
}
