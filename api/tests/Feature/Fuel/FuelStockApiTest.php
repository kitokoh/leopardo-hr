<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockMovement;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Stocks, cuves et rapprochement FuelStation — FUEL-009 (issue #5803).
 *
 * Couvre : livraison → niveau de stock incrémenté, ajustement (raison
 * obligatoire), idempotence par external_id, rapprochement rejouable (upsert
 * par clé unique, écart jamais silencieux), 404 sûr cross-tenant, RBAC.
 */
class FuelStockApiTest extends TestCase
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
            'code' => 'ST-001',
            'name' => 'Centrale',
            'timezone' => 'UTC',
        ]);

        return [$company, $manager, $operator, $station];
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/stations/1/stock')->assertStatus(401);
        $this->postJson('/api/v1/fuel-station/stations/1/deliveries', [])->assertStatus(401);
    }

    public function test_operator_cannot_manage_stock(): void
    {
        [$company, , $operator, $station] = $this->setupCompany();
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/fuel-station/stations/'.$station->id.'/stock')->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/deliveries', [
            'product_type' => 'essence',
            'quantity_minor' => 1000,
        ])->assertStatus(403);
    }

    public function test_manager_records_delivery_and_stock_increases(): void
    {
        [$company, $manager, , $station] = $this->setupCompany();
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/deliveries', [
            'product_type' => 'essence',
            'quantity_minor' => 10000,
            'external_id' => 'SUP-2026-001',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.quantity_minor', 10000)
            ->assertJsonPath('data.status', 'received');

        $this->getJson('/api/v1/fuel-station/stations/'.$station->id.'/stock')
            ->assertStatus(200)
            ->assertJsonPath('data.0.product_type', 'essence')
            ->assertJsonPath('data.0.current_level_minor', 10000);

        // Rejeu avec le même external_id → idempotent (pas de doublon).
        $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/deliveries', [
            'product_type' => 'essence',
            'quantity_minor' => 10000,
            'external_id' => 'SUP-2026-001',
        ])->assertStatus(201);

        $this->getJson('/api/v1/fuel-station/stations/'.$station->id.'/stock')
            ->assertJsonPath('data.0.current_level_minor', 10000);
    }

    public function test_adjustment_requires_reason(): void
    {
        [$company, $manager, , $station] = $this->setupCompany();
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/adjustments', [
            'product_type' => 'essence',
            'quantity_minor' => -500,
        ])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/adjustments', [
            'product_type' => 'essence',
            'quantity_minor' => -500,
            'reason' => 'Écart de jauge constaté',
            'idempotency_key' => 'adj-2026-08-30-1',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.quantity_minor', -500);

        // Rejeu idempotent via idempotency_key.
        $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/adjustments', [
            'product_type' => 'essence',
            'quantity_minor' => -500,
            'reason' => 'Écart de jauge constaté',
            'idempotency_key' => 'adj-2026-08-30-1',
        ])->assertStatus(201);

        $this->getJson('/api/v1/fuel-station/stations/'.$station->id.'/stock')
            ->assertJsonPath('data.0.current_level_minor', -500);
    }

    public function test_reconciliation_is_replayable_and_never_silent(): void
    {
        [$company, $manager, , $station] = $this->setupCompany();
        Sanctum::actingAs($manager);

        // Une livraison hier, une vente aujourd'hui (mouvement sale −).
        $day = Carbon::today()->toDateString();

        FuelStockMovement::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'product_type' => 'essence',
            'type' => FuelStockMovement::TYPE_DELIVERY,
            'quantity_minor' => 10000,
            'recorded_by' => $manager->id,
            'recorded_at' => Carbon::yesterday()->startOfDay()->addHours(8),
        ]);
        FuelStockMovement::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'product_type' => 'essence',
            'type' => FuelStockMovement::TYPE_SALE,
            'quantity_minor' => -2500,
            'recorded_by' => $manager->id,
            'recorded_at' => Carbon::today()->startOfDay()->addHours(10),
        ]);

        $service = $this->app->make(FuelStockService::class);
        $first = $service->reconcile($company->id, (int) $station->id, $day);
        $second = $service->reconcile($company->id, (int) $station->id, $day);

        // Rejouable : même nombre de snapshots, mêmes valeurs.
        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame($first[0]->opening_minor, $second[0]->opening_minor);
        $this->assertSame(10000, $first[0]->deliveries_minor);
        $this->assertSame(-2500, $first[0]->sales_minor);
        $this->assertSame(7500, $first[0]->expected_closing_minor);

        $this->getJson('/api/v1/fuel-station/stations/'.$station->id.'/reconciliations?day='.$day)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'balanced');
    }

    public function test_reconciliation_reports_variance_when_metered_delta_differs(): void
    {
        [$company, $manager, , $station] = $this->setupCompany();

        /** @var FuelPump $pump */
        $pump = FuelPump::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'code' => 'P-1',
            'product_types' => ['essence'],
        ]);
        /** @var FuelMeterRegister $register */
        $register = FuelMeterRegister::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'pump_id' => $pump->id,
            'meter_code' => 'M-1',
            'product_code' => 'essence',
        ]);

        $day = Carbon::today()->toDateString();

        FuelStockMovement::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'product_type' => 'essence',
            'type' => FuelStockMovement::TYPE_DELIVERY,
            'quantity_minor' => 10000,
            'recorded_by' => $manager->id,
            'recorded_at' => Carbon::today()->startOfDay()->addHours(8),
        ]);

        FuelMeterInterval::query()->create([
            'company_id' => $company->id,
            'meter_id' => $register->id,
            'previous_reading_id' => 1,
            'current_reading_id' => 2,
            'previous_value_minor' => 0,
            'current_value_minor' => 8000,
            'delta_minor' => 8000,
            'interval_seconds' => 3600,
            'calculated_at' => Carbon::today()->startOfDay()->addHours(18),
            'calculation_status' => 'valid',
        ]);

        $service = $this->app->make(FuelStockService::class);
        $snapshots = $service->reconcile($company->id, (int) $station->id, $day);

        $this->assertCount(1, $snapshots);
        $this->assertSame(10000, $snapshots[0]->expected_closing_minor);
        $this->assertSame(8000, $snapshots[0]->metered_delta_minor);
        $this->assertSame(2000, $snapshots[0]->variance_minor);
        $this->assertSame('variance', $snapshots[0]->status);
    }

    public function test_cross_tenant_stock_is_404(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);
        [$companyA] = $this->setupCompany();

        /** @var FuelStation $stationA */
        $stationA = FuelStation::query()->where('company_id', $companyA->id)->firstOrFail();

        Sanctum::actingAs($managerB);
        $this->getJson('/api/v1/fuel-station/stations/'.$stationA->id.'/stock')->assertStatus(404);
        $this->postJson('/api/v1/fuel-station/stations/'.$stationA->id.'/deliveries', [
            'product_type' => 'essence',
            'quantity_minor' => 100,
        ])->assertStatus(404);
    }
}
