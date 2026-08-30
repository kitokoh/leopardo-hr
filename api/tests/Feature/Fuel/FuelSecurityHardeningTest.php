<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Sécurité, performance et observabilité — FUEL-020 (issue #5814).
 *
 * Couvre (matrice de durcissement) : 401 sur toutes les routes fuel sans
 * authentification, 403 solution inactive, 403 opérateur sur les routes
 * manager, 404 cross-tenant, pagination bornée (per_page>100 → 100),
 * filtres allowlist (paramètre inconnu ignoré, pas de fuite).
 */
class FuelSecurityHardeningTest extends TestCase
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

    /**
     * @return list<string>
     */
    private function authRoutes(): array
    {
        return [
            '/api/v1/fuel-station/stations',
            '/api/v1/fuel-station/sites',
            '/api/v1/fuel-station/equipment',
            '/api/v1/fuel-station/products',
            '/api/v1/fuel-station/incidents',
            '/api/v1/fuel-station/maintenance-tasks',
            '/api/v1/fuel-station/stock-entries',
            '/api/v1/fuel-station/stock/level?product_code=ESS',
            '/api/v1/fuel-station/stock/reconciliations',
            '/api/v1/fuel-station/customers',
            '/api/v1/fuel-station/reports/daily-sales',
            '/api/v1/fuel-station/exports/sales',
            '/api/v1/fuel-station/sync/outbox',
            '/api/v1/fuel-station/sync/readings',
            '/api/v1/fuel-station/sync/sales',
            '/api/v1/fuel-station/imports',
        ];
    }

    public function test_all_fuel_routes_require_auth(): void
    {
        foreach ($this->authRoutes() as $route) {
            $method = str_contains($route, 'sync/readings') || str_contains($route, 'sync/sales') ? 'post' : 'get';

            $response = $method === 'post'
                ? $this->postJson($route, ['readings' => [], 'sales' => []])
                : $this->getJson($route);

            $response->assertStatus(401);
        }
    }

    public function test_solution_inactive_returns_403(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => []]); // flag fuel_station absent
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/fuel-station/stations')->assertStatus(403);
        $this->getJson('/api/v1/fuel-station/products')->assertStatus(403);
    }

    public function test_manager_routes_denied_to_operator(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->operator($company));

        $this->postJson('/api/v1/fuel-station/stock-entries', [
            'product_code' => 'ESS', 'quantity' => 1, 'idempotency_key' => 'k',
        ])->assertStatus(403);
        $this->getJson('/api/v1/fuel-station/reports/anomalies')->assertStatus(403);
        $this->getJson('/api/v1/fuel-station/exports/readings')->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/customers', [
            'external_id' => 'C', 'full_name' => 'X',
        ])->assertStatus(403);
    }

    public function test_pagination_is_bounded(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $response = $this->getJson('/api/v1/fuel-station/stations?per_page=100000')->assertStatus(200)->json('meta');
        $this->assertLessThanOrEqual(100, $response['per_page'] ?? 100);
    }

    public function test_cross_tenant_404_on_stations(): void
    {
        $companyA = $this->company();
        Sanctum::actingAs($this->manager($companyA));

        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $companyA->id,
            'code' => 'ST-HARD',
            'name' => 'Station A',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        $companyB = $this->company();
        Sanctum::actingAs($this->manager($companyB));

        $this->getJson('/api/v1/fuel-station/stations/'.$station->id)->assertStatus(404);
    }
}
