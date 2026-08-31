<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockEntry;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Stocks, cuves et rapprochement — FUEL-009 (issue #5803).
 *
 * Couvre : entrée de stock idempotente (idempotency_key), ajustement sans
 * motif REFUSÉ (aucun ajustement silencieux), rejet d'un opérateur
 * non-manager, rapprochement idempotent par station/jour (rejeu sans
 * doublon), niveau de stock calculé, seuil bas → événement outbox.
 */
class FuelStockApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        return $company;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);

        return $operator;
    }

    private function station(Company $company): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.substr((string) $company->id, 0, 8),
            'name' => 'Station test',
            'timezone' => 'UTC',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->postJson('/api/v1/fuel-station/stock-entries', [
            'product_code' => 'ESS',
            'quantity' => 1000,
            'idempotency_key' => 'k1',
        ])->assertStatus(401);
    }

    public function test_operator_cannot_record_stock_entry(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->operator($company));

        $this->postJson('/api/v1/fuel-station/stock-entries', [
            'product_code' => 'ESS',
            'quantity' => 1000,
            'idempotency_key' => 'k1',
        ])->assertStatus(403);
    }

    public function test_manager_records_stock_entry_idempotently(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        Sanctum::actingAs($this->manager($company));

        $payload = [
            'station_id' => $station->id,
            'product_code' => 'ESS',
            'quantity' => 5000,
            'unit_cost' => 120,
            'entry_type' => 'delivery',
            'reference' => 'INV-2026-0001',
            'idempotency_key' => 'stock-delivery-0001',
        ];

        /** @var array<string, mixed> $first */
        $first = $this->postJson('/api/v1/fuel-station/stock-entries', $payload)
            ->assertStatus(201)
            ->json('data');

        /** @var array<string, mixed> $second */
        $second = $this->postJson('/api/v1/fuel-station/stock-entries', $payload)->assertStatus(201)->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, FuelStockEntry::query()->where('company_id', $company->id)->count());

        // Niveau de stock calculé = entrées − ventes.
        $level = $this->getJson('/api/v1/fuel-station/stock/level?station_id='.$station->id.'&product_code=ESS')
            ->assertStatus(200)
            ->json('data.level_litres');
        $this->assertEqualsWithDelta(5000.0, (float) $level, 0.001);
    }

    public function test_adjustment_without_reason_is_rejected(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        Sanctum::actingAs($this->manager($company));

        $this->postJson('/api/v1/fuel-station/stock-entries', [
            'station_id' => $station->id,
            'product_code' => 'ESS',
            'quantity' => -200,
            'entry_type' => 'adjustment',
            'idempotency_key' => 'adj-no-reason',
        ])->assertStatus(422);

        // Aucun ajustement silencieux : rien n'a été écrit.
        $this->assertSame(0, FuelStockEntry::query()->where('company_id', $company->id)->count());
    }

    public function test_reconciliation_is_idempotent_per_station_and_date(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        Sanctum::actingAs($this->manager($company));

        $this->postJson('/api/v1/fuel-station/stock-entries', [
            'station_id' => $station->id,
            'product_code' => 'ESS',
            'quantity' => 10000,
            'idempotency_key' => 'stock-0001',
        ])->assertStatus(201);

        // Premier rapprochement.
        $this->postJson('/api/v1/fuel-station/stock/reconcile?station_id='.$station->id.'&date='.now()->subDay()->toDateString())
            ->assertStatus(200)
            ->assertJsonPath('data.run.status', FuelReconciliationRun::STATUS_COMPLETED);

        $count = FuelReconciliationRun::query()->where('company_id', $company->id)->count();
        $this->assertSame(1, $count);

        // Rejeu : même run mis à jour, aucun doublon.
        $this->postJson('/api/v1/fuel-station/stock/reconcile?station_id='.$station->id.'&date='.now()->subDay()->toDateString())
            ->assertStatus(200);

        $this->assertSame(1, FuelReconciliationRun::query()->where('company_id', $company->id)->count());
    }

    public function test_low_stock_publishes_threshold_event(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        Sanctum::actingAs($this->manager($company));

        // Petite livraison → sous le seuil (500 L).
        $this->postJson('/api/v1/fuel-station/stock-entries', [
            'station_id' => $station->id,
            'product_code' => 'GPL',
            'quantity' => 100,
            'idempotency_key' => 'stock-small',
        ])->assertStatus(201);

        $events = DB::table('fuel_outbox_events')
            ->where('company_id', $company->id)
            ->where('event_type', 'fuel.stock.threshold.breached.v1')
            ->count();

        $this->assertSame(1, $events);
    }
}
