<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6712 — le dashboard admin « Fuel stations » appelle 3 endpoints
 * (stations, incidents, reconciliations) qui n'existaient pas → 3 toasts
 * d'erreur permanents. Le référentiel read-only est désormais servi sur les
 * VRAIES tables fuel.
 */
class FuelStationReferentialTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        // #8130 — clé `features` exacte exigée par assertSolutionActive()
        // (fuel_station) : sans elle, 403 FEATURE_NOT_ENABLED avant tout test.
        $company = Company::factory()->withFeature('fuel_station')->create([
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'country' => 'DZ',
            'currency' => 'DZD',
            'timezone' => 'Africa/Algiers',
        ]);
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;

        DB::statement('SET search_path TO shared_tenants,public');
    }

    public function test_stations_endpoint_lists_fuel_stations(): void
    {
        DB::table('fuel_stations')->insert([
            'company_id' => $this->company->id,
            'code' => 'ST-001',
            'name' => 'Station Alger Centre',
            'timezone' => 'Africa/Algiers',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->getJson('/api/v1/fuel-station/stations?per_page=100')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'ST-001')
            ->assertJsonPath('data.0.name', 'Station Alger Centre')
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_incidents_endpoint_derives_from_inactive_equipment(): void
    {
        $stationId = DB::table('fuel_stations')->insertGetId([
            'company_id' => $this->company->id,
            'code' => 'ST-002',
            'name' => 'Station Oran',
            'timezone' => 'Africa/Algiers',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fuel_pumps')->insert([
            'company_id' => $this->company->id,
            'station_id' => $stationId,
            'code' => 'P-01',
            'status' => 'retired',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->getJson('/api/v1/fuel-station/incidents?per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.equipment_type', 'pump')
            ->assertJsonPath('data.0.priority', 'high');
    }

    public function test_reconciliations_endpoint_maps_pending_review(): void
    {
        $stationId = DB::table('fuel_stations')->insertGetId([
            'company_id' => $this->company->id,
            'code' => 'ST-003',
            'name' => 'Station Blida',
            'timezone' => 'Africa/Algiers',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fuel_cash_sessions')->insert([
            'company_id' => $this->company->id,
            'station_id' => $stationId,
            'opened_by' => $this->manager->id,
            'opened_at' => now()->subDay(),
            'closed_at' => now(),
            'opening_balance' => 1000,
            'closing_balance' => 1500,
            'expected_balance' => 1490,
            'variance' => 10,
            'status' => 'closed',
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->getJson('/api/v1/fuel-station/reconciliations?status=pending_review&per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'closed')
            ->assertJsonPath('data.0.variance', '10.00');
    }

    /**
     * #8188 (option 2) — contrat explicite, fin de la zone grise : la
     * LECTURE du référentiel est volontairement ouverte à tout employé
     * authentifié du tenant (#7439 : l'écran pompiste web/mobile appelle
     * GET /fuel-station/stations|stations/{id} en employé simple ;
     * FuelStationPolicy::viewAny() = true). Ce test échoue si la lecture
     * redevient manager-only OU si elle fuit hors du tenant courant.
     */
    public function test_referential_read_is_open_to_any_tenant_employee(): void
    {
        $stationId = DB::table('fuel_stations')->insertGetId([
            'company_id' => $this->company->id,
            'code' => 'ST-004',
            'name' => 'Station Hydra',
            'timezone' => 'Africa/Algiers',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        // Le flag fuel_station est ACTIF (setUp) : un 200 prouve que la
        // requête atteint réellement la route et la policy dédiée.
        $this->actingAs($employee)
            ->getJson('/api/v1/fuel-station/stations')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'ST-004')
            ->assertJsonPath('data.0.name', 'Station Hydra');

        $this->actingAs($employee)
            ->getJson("/api/v1/fuel-station/stations/{$stationId}")
            ->assertOk()
            ->assertJsonPath('data.code', 'ST-004');
    }

    /**
     * #8188 — la garde de rôle reste obligatoire sur les ÉCRITURES du
     * référentiel. Le flag fuel_station est actif (setUp) : le 403 vient
     * donc du middleware `api.manager` (code MANAGER_REQUIRED), pas du
     * flag — l'assertion échoue si la garde disparaît du groupe manager.
     */
    public function test_referential_write_requires_manager_role(): void
    {
        $stationId = DB::table('fuel_stations')->insertGetId([
            'company_id' => $this->company->id,
            'code' => 'ST-005',
            'name' => 'Station Bir Mourad Raïs',
            'timezone' => 'Africa/Algiers',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $this->actingAs($employee)
            ->postJson('/api/v1/fuel-station/stations', [
                'code' => 'ST-999',
                'name' => 'Station sauvage',
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'MANAGER_REQUIRED');

        $this->actingAs($employee)
            ->putJson("/api/v1/fuel-station/stations/{$stationId}", [
                'name' => 'Renommée sans droit',
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'MANAGER_REQUIRED');

        $this->actingAs($employee)
            ->deleteJson("/api/v1/fuel-station/stations/{$stationId}")
            ->assertForbidden()
            ->assertJsonPath('error', 'MANAGER_REQUIRED');
    }
}
