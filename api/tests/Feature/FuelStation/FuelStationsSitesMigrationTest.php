<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Migrations FuelStation stations/sites — Issue #5796 (FUEL-002).
 *
 * Verrouille :
 *   1. fresh/re-run/rollback validés (idempotence + down) ;
 *   2. aucune ligne sans company_id (NOT NULL sur chaque table) ;
 *   3. tests d'isolation : FK composite (station_id, company_id) rend toute
 *      référence cross-tenant impossible ;
 *   4. index tenant-first présents (company_id en tête) ;
 *   5. contraintes CHECK de statut.
 */
class FuelStationsSitesMigrationTest extends TestCase
{
    use RefreshTenantDatabase;

    private const MIGRATION = '2026_08_28_000401_5796_create_fuel_stations_sites_tables';

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

    public function test_tables_are_created_in_tenant_schema(): void
    {
        foreach (['fuel_stations', 'fuel_sites'] as $table) {
            $row = DB::selectOne(
                'SELECT t.table_schema FROM information_schema.tables t WHERE t.table_name = ? LIMIT 1',
                [$table]
            );
            $this->assertSame('shared_tenants', $row->table_schema ?? null, "table {$table} absente du schéma tenant");
        }
    }

    public function test_company_id_is_non_nullable(): void
    {
        $this->expectException(QueryException::class);

        FuelStation::query()->create([
            'code' => 'ST-1',
            'name' => 'Sans tenant',
        ]);
    }

    public function test_status_check_rejects_unknown_status(): void
    {
        try {
            FuelStation::query()->create([
                'company_id' => $this->companyA->id,
                'code' => 'ST-1',
                'name' => 'Station A',
                'status' => 'vaporized',
            ]);
            $this->fail('Le CHECK fuel_stations_status_check aurait dû rejeter le statut.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_stations_status_check', $exception->getMessage());
        }
    }

    public function test_cross_tenant_site_reference_is_rejected_by_composite_fk(): void
    {
        /** @var FuelStation $stationA */
        $stationA = FuelStation::query()->create([
            'company_id' => $this->companyA->id,
            'code' => 'ST-A',
            'name' => 'Station A',
        ]);

        try {
            // Site du tenant B référençant une station du tenant A → violation FK composite.
            FuelSite::query()->create([
                'company_id' => $this->companyB->id,
                'station_id' => $stationA->id,
                'code' => 'SITE-B',
                'name' => 'Site B',
            ]);
            $this->fail('La FK composite (station_id, company_id) aurait dû rejeter la référence cross-tenant.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_sites_station_company_fk', $exception->getMessage());
        }
    }

    public function test_tenant_first_indexes_exist(): void
    {
        $indexes = DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename IN ('fuel_stations','fuel_sites')"
        );
        $names = array_column($indexes, 'indexname');

        foreach (['fuel_stations_company_status_idx', 'fuel_stations_company_created_idx', 'fuel_sites_company_station_idx'] as $expected) {
            $this->assertContains($expected, $names, "index tenant-first manquant : {$expected}");
        }
    }

    public function test_valid_inserts_and_relations(): void
    {
        app()->instance('current_company', $this->companyA);

        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'code' => 'ST-OK',
            'name' => 'Station OK',
        ]);

        /** @var FuelSite $site */
        $site = $station->sites()->create([
            'code' => 'SITE-1',
            'name' => 'Site principal',
        ]);

        $this->assertSame($station->id, (int) $site->station_id);
        $this->assertSame(1, FuelStation::query()->count());
        $this->assertSame(1, FuelSite::query()->count());
    }

    public function test_migration_is_idempotent_and_rollback_works(): void
    {
        $migration = require database_path('migrations/tenant/'.self::MIGRATION.'.php');

        // Re-run : les gardes schemaTableExists absorbent le rejeu.
        $migration->up();
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('fuel_stations'));

        // Rollback complet.
        $migration->down();
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('fuel_stations'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('fuel_sites'));
    }
}
