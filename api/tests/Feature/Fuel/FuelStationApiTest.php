<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API & Policies FuelStation (FUEL-011 #5805) + sécurité/perf (FUEL-020 #5814).
 *
 * FUEL-011 : routes OpenAPI, policies deny-by-default, tri/filtres allowlist,
 * pagination bornée, tests 401/403/404/tenant.
 * FUEL-020 : throttle sur toutes les routes fuel, index N+1 guardé (eager
 * loading), index vérifiés, audit des transitions sans PII.
 */
class FuelStationApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        return $company;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/stations')->assertStatus(401);
        $this->postJson('/api/v1/fuel-station/stations', ['code' => 'X', 'name' => 'X'])->assertStatus(401);
    }

    public function test_manager_crud_station_and_operator_reads(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $created = $this->postJson('/api/v1/fuel-station/stations', [
            'code' => 'ST-ALG-02',
            'name' => 'Station Alger 2',
            'address' => '12 rue des Pins',
            'timezone' => 'Africa/Algiers',
            'currency' => 'DZD',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ST-ALG-02')
            ->assertJsonPath('data.name', 'Station Alger 2');

        $stationId = $created->json('data.id');

        $this->getJson('/api/v1/fuel-station/stations')->assertOk();
        $this->getJson("/api/v1/fuel-station/stations/{$stationId}")->assertOk();

        $this->putJson("/api/v1/fuel-station/stations/{$stationId}", [
            'name' => 'Station Alger 2 Bis',
        ])->assertOk()->assertJsonPath('data.name', 'Station Alger 2 Bis');

        // Opérateur : lecture autorisée, écriture refusée (deny-by-default).
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/fuel-station/stations')->assertOk();
        $this->postJson('/api/v1/fuel-station/stations', ['code' => 'ST-XX', 'name' => 'X'])->assertForbidden();

        // Manager : suppression.
        Sanctum::actingAs($manager);
        $this->deleteJson("/api/v1/fuel-station/stations/{$stationId}")->assertOk();
        $this->getJson("/api/v1/fuel-station/stations/{$stationId}")->assertNotFound();
    }

    public function test_cross_tenant_station_is_404(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        /** @var FuelStation $stationA */
        $stationA = FuelStation::query()->create([
            'company_id' => $companyA->id,
            'code' => 'ST-A',
            'name' => 'Station A',
            'timezone' => 'UTC',
            'status' => 'active',
        ]);

        Sanctum::actingAs($managerB);

        $this->getJson("/api/v1/fuel-station/stations/{$stationA->id}")->assertNotFound();
        $this->putJson("/api/v1/fuel-station/stations/{$stationA->id}", ['name' => 'Hack'])->assertNotFound();
        $this->getJson("/api/v1/fuel-station/stations/{$stationA->id}/pumps")->assertNotFound();
    }

    public function test_index_filters_and_sort_are_allowlisted_with_bounded_pagination(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        foreach (['ST-01', 'ST-02', 'ST-03'] as $code) {
            FuelStation::query()->create([
                'company_id' => $company->id,
                'code' => $code,
                'name' => "Station {$code}",
                'timezone' => 'UTC',
                'status' => $code === 'ST-03' ? 'inactive' : 'active',
            ]);
        }

        Sanctum::actingAs($manager);

        // Filtre allowlist + tri.
        $this->getJson('/api/v1/fuel-station/stations?status=active&sort_by=code&sort_dir=asc')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');

        $this->getJson('/api/v1/fuel-station/stations?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');

        // Tri arbitraire ignoré (allowlist) — pas d'erreur, pas de SQL arbitraire.
        $this->getJson('/api/v1/fuel-station/stations?sort_by=password_hash')
            ->assertOk();
    }

    public function test_equipment_crud_scoped_by_station(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-EQ',
            'name' => 'Station Equipement',
            'timezone' => 'UTC',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/fuel-station/stations/{$station->id}/pumps", ['code' => 'P1'])
            ->assertCreated();

        $this->postJson("/api/v1/fuel-station/stations/{$station->id}/tanks", [
            'code' => 'TK-1',
            'product_type' => 'ESS95',
            'capacity_minor' => 10000,
        ])->assertCreated();

        $this->postJson('/api/v1/fuel-station/products', [
            'code' => 'ESS95',
            'name' => 'Essence 95',
        ])->assertCreated();

        $this->getJson("/api/v1/fuel-station/stations/{$station->id}/pumps")
            ->assertOk()
            ->assertJsonCount(1, 'data.data');

        $this->getJson('/api/v1/fuel-station/products')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_all_fuel_routes_are_throttled(): void
    {
        // FUEL-020 : toute route /fuel-station/* porte throttle:api (+ api-plan).
        $missing = [];

        /** @var Route $route */
        foreach (RouteFacade::getRoutes() as $route) {
            $uri = (string) $route->uri();

            if (! str_contains($uri, 'fuel-station')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $hasThrottle = collect($middleware)->contains(fn ($m): bool => is_string($m) && str_starts_with($m, 'throttle:api'));

            if (! $hasThrottle) {
                $missing[] = $uri;
            }
        }

        self::assertSame([], $missing, 'toutes les routes fuel doivent être throttlées (FUEL-020)');
    }

    public function test_station_index_does_not_n_plus_1(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        foreach (['ST-01', 'ST-02', 'ST-03', 'ST-04', 'ST-05'] as $code) {
            FuelStation::query()->create([
                'company_id' => $company->id,
                'code' => $code,
                'name' => "Station {$code}",
                'timezone' => 'UTC',
                'status' => 'active',
            ]);
        }

        Sanctum::actingAs($manager);

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();

        $this->getJson('/api/v1/fuel-station/stations')->assertOk();

        $queries = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // 1 requête pagination + 1 count — aucun N+1 (5 stations ≤ 3 requêtes).
        self::assertLessThanOrEqual(3, $queries, "index stations en {$queries} requêtes — N+1 détecté");
    }

    public function test_fuel_indexes_exist_for_volume_tables(): void
    {
        $indexes = collect(\Illuminate\Support\Facades\DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename IN ('fuel_sales','fuel_stock_movements','fuel_incidents','fuel_maintenance_tasks')"
        ))->pluck('indexname')->all();

        // Index de croissance exigés (FUEL-020).
        self::assertContains('fuel_sales_company_time_idx', $indexes);
        self::assertContains('fuel_stock_movements_company_tank_time_idx', $indexes);
        self::assertContains('fuel_incidents_company_status_idx', $indexes);
        self::assertContains('fuel_maint_tasks_company_status_idx', $indexes);
    }
}
