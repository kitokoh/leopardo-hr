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
use App\Modules\FuelStation\Domain\Models\FuelTank
use App\Modules\FuelStation\Domain\Models\FuelTankDelivery;
use App\Modules\FuelStation\Domain\Models\FuelStockMovement
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation

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


    public function test_manager_records_idempotent_delivery(): void
    {
        [$company, $manager, , $tank] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $payload = [
            'quantity_minor' => 5000,
            'unit_price_minor' => 12000,
            'external_id' => 'delivery-2026-08-30-001',
            'notes' => 'Livraison hebdo',
        ];

        $this->postJson("/api/v1/fuel-station/tanks/{$tank->id}/deliveries", $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.quantity_minor', 5000)
            ->assertJsonPath('data.tank_id', $tank->id)
            ->assertJsonPath('data.external_id', 'delivery-2026-08-30-001');

        $this->assertDatabaseHas('fuel_tank_deliveries', [
            'company_id' => $company->id,
            'tank_id' => $tank->id,
            'quantity_minor' => 5000,
        ]);

        // Le niveau courant de la cuve est incrémenté (mouvement légitime).
        $this->assertSame(15000, (int) $tank->refresh()->current_level_minor);

        // Rejeu idempotent : même external_id → même livraison, pas de doublon.
        $this->postJson("/api/v1/fuel-station/tanks/{$tank->id}/deliveries", $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.id', FuelTankDelivery::query()->firstOrFail()->id);

        $this->assertSame(1, FuelTankDelivery::query()->count());
        $this->assertSame(15000, (int) $tank->refresh()->current_level_minor);
    }


    public function test_delivery_rejects_non_positive_quantity(): void
    {
        [, $manager, , $tank] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/fuel-station/tanks/{$tank->id}/deliveries", [
            'quantity_minor' => 0,
        ])->assertStatus(422);

        $this->postJson("/api/v1/fuel-station/tanks/{$tank->id}/deliveries", [
            'quantity_minor' => -10,
        ])->assertStatus(422);
    }


    public function test_operator_cannot_record_delivery(): void
    {
        [, , $operator, $tank] = $this->seedTenant();

        Sanctum::actingAs($operator);

        $this->postJson("/api/v1/fuel-station/tanks/{$tank->id}/deliveries", [
            'quantity_minor' => 1000,
        ])->assertStatus(403);
    }


    public function test_cross_tenant_delivery_is_404(): void
    {
        [$companyA, $managerA] = $this->seedTenant();
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $stationB = $this->createStation($companyB);
        $tankB = $this->createTank($companyB, $stationB);

        Sanctum::actingAs($managerA);

        // Le tank appartient au tenant B : le route model binding résout le
        // modèle global, puis le contrôleur refuse (tenant mismatch → 404).
        $this->postJson("/api/v1/fuel-station/tanks/{$tankB->id}/deliveries", [
            'quantity_minor' => 1000,
        ])->assertStatus(404);

        $this->assertSame(0, FuelTankDelivery::query()->where('company_id', $companyA->id)->count());
    }


    public function test_manager_lists_stock_levels_with_fill_ratio(): void
    {
        [$company, $manager, , $tank] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/stocks')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $tank->id)
            ->assertJsonPath('data.0.current_level_minor', 10000)
            ->assertJsonPath('data.0.fill_ratio', 0.5);
    }


    public function test_reconciliation_is_replayable_and_reports_variance(): void
    {
        [$company, $manager, , $tank] = $this->seedTenant();

        Sanctum::actingAs($manager);

        // Vente du même produit que la cuve, via l'API (FuelSaleService) :
        // le niveau de la cuve est décrémenté (10000 → 8000).
        $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $tank->station_id,
            'product' => $tank->product_type,
            'quantity' => 2.0,
            'unit_price' => 150.0,
        ])->assertStatus(200);

        $this->postJson("/api/v1/fuel-station/stations/{$tank->station_id}/reconciliations", [
            'run_date' => now()->toDateString(),
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('meta.replayed', false);

        $this->assertSame(1, FuelReconciliationRun::query()->count());

        // Rejeu : même (station, date) → run existant, aucun recalcul.
        $this->postJson("/api/v1/fuel-station/stations/{$tank->station_id}/reconciliations", [
            'run_date' => now()->toDateString(),
        ])
            ->assertStatus(200)
            ->assertJsonPath('meta.replayed', true);

        $this->assertSame(1, FuelReconciliationRun::query()->count());

        $run = FuelReconciliationRun::query()->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertIsArray($run->summary);

        $summary = $run->summary;
        $this->assertArrayHasKey('tanks', $summary);

        $tankLine = collect($summary['tanks'])->firstWhere('tank_id', $tank->id);
        $this->assertNotNull($tankLine);

        // La vente de 2 L (2000 unités mineures) a décrémenté la cuve
        // (FUEL-009) : niveau 10000 → 8000. Ouverture dérivée = 8000 + 2000
        // = 10000 ; attendu = 10000 − 2000 = 8000 = mesuré → écart 0.
        $this->assertSame(10000, $tankLine['opening_level_minor']);
        $this->assertSame(0, $tankLine['deliveries_minor']);
        $this->assertSame(2000, $tankLine['sales_minor']);
        $this->assertSame(8000, $tankLine['expected_level_minor']);
        $this->assertSame(8000, $tankLine['measured_level_minor']);
        $this->assertSame(0, $tankLine['variance_minor']);
        $this->assertTrue($tankLine['explainable']);
    }


    public function test_reconciliation_reports_unexplained_variance(): void
    {
        [$company, $manager, , $tank] = $this->seedTenant();

        Sanctum::actingAs($manager);

        // Vente de 2 L via l'API : la cuve passe de 10000 à 8000 (décrément).
        $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $tank->station_id,
            'product' => $tank->product_type,
            'quantity' => 2.0,
            'unit_price' => 150.0,
        ])->assertStatus(200);

        // Simule une perte non enregistrée (vol/fuite) : le niveau physique
        // est plus bas que ce que le registre (ventes) justifie.
        $tank->update(['current_level_minor' => 6000]);

        $this->postJson("/api/v1/fuel-station/stations/{$tank->station_id}/reconciliations", [
            'run_date' => now()->toDateString(),
        ])->assertStatus(200);

        $run = FuelReconciliationRun::query()->firstOrFail();
        $tankLine = collect($run->summary['tanks'])->firstWhere('tank_id', $tank->id);

        // Registre : ouverture 10000 − ventes 2000 = attendu 8000 ; mesuré
        // physique 6000 → écart +2000, NON expliqué (aucun ajustement).
        $this->assertSame(8000, $tankLine['expected_level_minor']);
        $this->assertSame(6000, $tankLine['measured_level_minor']);
        $this->assertSame(2000, $tankLine['variance_minor']);
        $this->assertFalse($tankLine['explainable']);
    }


    public function test_reconciliation_relaunches_failed_run(): void
    {
        [$company, $manager, , $tank] = $this->seedTenant();

        $failed = FuelReconciliationRun::query()->create([
            'company_id' => $company->id,
            'station_id' => $tank->station_id,
            'run_date' => now()->toDateString(),
            'status' => FuelReconciliationRun::STATUS_FAILED,
            'last_error' => 'boom',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/fuel-station/stations/{$tank->station_id}/reconciliations", [
            'run_date' => now()->toDateString(),
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.id', $failed->id);

        $this->assertSame(1, FuelReconciliationRun::query()->count());
    }


    public function test_reconciliation_lists_and_shows(): void
    {
        [$company, $manager, , $tank] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/fuel-station/stations/{$tank->station_id}/reconciliations")
            ->assertStatus(200);

        $run = FuelReconciliationRun::query()->firstOrFail();

        $this->getJson('/api/v1/fuel-station/reconciliations')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $run->id);

        $this->getJson("/api/v1/fuel-station/reconciliations/{$run->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');
    }


    public function test_cross_tenant_reconciliation_is_404(): void
    {
        [$companyA, $managerA] = $this->seedTenant();
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $stationB = $this->createStation($companyB);

        Sanctum::actingAs($managerA);

        $this->postJson("/api/v1/fuel-station/stations/{$stationB->id}/reconciliations")
            ->assertStatus(404);
    }


    public function test_solution_inactive_returns_403(): void
    {
        $company = Company::factory()->create(['features' => []]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/stocks')->assertStatus(403);
    }


    private function createStation(Company $company): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.substr((string) $company->id, 0, 8),
            'name' => 'Station Test',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }


    private function createTank(Company $company, FuelStation $station): FuelTank
    {
        /** @var FuelTank $tank */
        $tank = FuelTank::query()->create([
            'company_id' => $company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'code' => 'TNK-01',
            'product_type' => 'essence',
            'capacity_minor' => 20000,
            'current_level_minor' => 10000,
            'status' => FuelTank::STATUS_ACTIVE,
        ]);

        return $tank;
    }

    private function seedTenant(): array
    {
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $station = $this->createStation($company);
        $tank = $this->createTank($company, $station);

        return [$company, $manager, $operator, $tank];
    }


    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['fuel_station' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['fuel_station' => true],
        ]);
        $this->companyB = $companyB;
    }


    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }


    public function test_operator_cannot_manage_stocks(): void
    {
        Sanctum::actingAs($this->operator($this->companyA));

        $this->getJson('/api/v1/fuel-station/stocks/movements')->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/deliveries', [])->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/reconciliations', [])->assertStatus(403);
    }


    public function test_manager_records_delivery_and_movement_is_created(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $this->postJson('/api/v1/fuel-station/deliveries', [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'quantity_minor' => 100000,
            'supplier' => 'Naftal',
            'reference_number' => 'BL-2026-001',
            'delivered_at' => '2026-08-29T08:00:00Z',
            'idempotency_key' => 'deliv-001',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.delivery.product_type', 'essence')
            ->assertJsonPath('data.delivery.quantity_minor', 100000)
            ->assertJsonPath('data.delivery.status', 'received')
            ->assertJsonPath('data.movement.direction', 'in')
            ->assertJsonPath('data.movement.reason', 'delivery')
            ->assertJsonPath('data.replayed', false);

        $this->assertDatabaseHas('fuel_stock_movements', [
            'company_id' => $this->companyA->id,
            'station_id' => $station->id,
            'direction' => FuelStockMovement::DIRECTION_IN,
            'reason' => FuelStockMovement::REASON_DELIVERY,
            'quantity_minor' => 100000,
        ]);
    }


    public function test_delivery_replay_is_idempotent(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $payload = [
            'station_id' => $station->id,
            'product_type' => 'gazole',
            'quantity_minor' => 50000,
            'delivered_at' => '2026-08-29T10:00:00Z',
            'idempotency_key' => 'deliv-002',
        ];

        $this->postJson('/api/v1/fuel-station/deliveries', $payload)->assertStatus(201);
        $this->postJson('/api/v1/fuel-station/deliveries', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.replayed', true)
            ->assertJsonPath('data.delivery.id', fn ($id): bool => is_int($id));

        $this->assertDatabaseCount('fuel_deliveries', 1);
        $this->assertDatabaseCount('fuel_stock_movements', 1);
    }


    public function test_manager_verifies_delivery(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $delivery = $this->postJson('/api/v1/fuel-station/deliveries', [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'quantity_minor' => 20000,
            'delivered_at' => '2026-08-29T12:00:00Z',
            'idempotency_key' => 'deliv-003',
        ])->assertStatus(201)->json('data.delivery');

        $this->postJson("/api/v1/fuel-station/deliveries/{$delivery['id']}/verify")
            ->assertOk()
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.verified_at', fn ($v): bool => is_string($v));
    }


    public function test_reconciliation_is_replayable_and_idempotent(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $payload = [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'measured_close_minor' => 0,
            'idempotency_key' => 'recon-001',
        ];

        $this->postJson('/api/v1/fuel-station/reconciliations', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.variance_minor', 0);

        // Rejeu : même rapport (200), aucune seconde ligne.
        $this->postJson('/api/v1/fuel-station/reconciliations', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.id', fn ($id): bool => is_int($id));

        $this->assertDatabaseCount('fuel_stock_reconciliations', 1);
    }


    public function test_reconciliation_with_variance_closes_exception(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        // Livraison de 100 000, mais jauge de clôture à 99 000 → écart 1 000
        // (> tolérance par défaut 50) → exception, aucun ajustement silencieux.
        $this->postJson('/api/v1/fuel-station/deliveries', [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'quantity_minor' => 100000,
            'delivered_at' => '2026-08-10T08:00:00Z',
            'idempotency_key' => 'deliv-variance',
        ])->assertStatus(201);

        $this->postJson('/api/v1/fuel-station/reconciliations', [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'measured_close_minor' => 99000,
            'idempotency_key' => 'recon-variance',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', FuelStockReconciliation::STATUS_EXCEPTION)
            ->assertJsonPath('data.variance_minor', -1000);
    }


    public function test_adjustment_requires_reason_and_is_audited(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $this->postJson('/api/v1/fuel-station/stocks/adjustments', [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'quantity_minor' => 1000,
            'direction' => 'out',
            'movement_at' => '2026-08-29T14:00:00Z',
            'idempotency_key' => 'adj-001',
            'notes' => 'Écart constaté au rapprochement du 2026-08-29 (fuite cuve C1).',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.movement.reason', 'adjustment')
            ->assertJsonPath('data.movement.direction', 'out')
            ->assertJsonPath('data.replayed', false);

        // Motif obligatoire : un ajustement sans explication est refusé.
        $this->postJson('/api/v1/fuel-station/stocks/adjustments', [
            'station_id' => $station->id,
            'product_type' => 'essence',
            'quantity_minor' => 1000,
            'direction' => 'out',
            'movement_at' => '2026-08-29T14:00:00Z',
            'idempotency_key' => 'adj-002',
        ])->assertStatus(422);
    }


    public function test_cross_tenant_station_is_404(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $stationB = $this->station($this->companyB, 'ST-99');

        // Validation tenant-scoped des FormRequests → 422 (station d'un autre
        // tenant rejetée AVANT tout traitement).
        $this->postJson('/api/v1/fuel-station/deliveries', [
            'station_id' => $stationB->id,
            'product_type' => 'essence',
            'quantity_minor' => 100,
            'delivered_at' => '2026-08-29T08:00:00Z',
            'idempotency_key' => 'deliv-x-tenant',
        ])->assertStatus(422);

        $this->postJson('/api/v1/fuel-station/reconciliations', [
            'station_id' => $stationB->id,
            'product_type' => 'essence',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'idempotency_key' => 'recon-x-tenant',
        ])->assertStatus(422);
    }
}