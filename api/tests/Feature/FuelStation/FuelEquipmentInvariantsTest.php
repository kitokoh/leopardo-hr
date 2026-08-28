<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelMeter;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Pompes, cuves, compteurs et équipements FuelStation — Issue #5797 (FUEL-003).
 *
 * Verrouille :
 *   1. références cross-tenant impossibles (FK composites vers fuel_sites,
 *      actives dès que #5796 est mergée) ;
 *   2. compteur actif UNIQUE par pompe (index partiel) ;
 *   3. capacité strictement positive et unités allowlistées (CHECK) ;
 *   4. cycles de vie (statuts CHECK) ;
 *   5. company_id NON nullable.
 */
class FuelEquipmentInvariantsTest extends TestCase
{
    use RefreshTenantDatabase;

    private const MIGRATION = '2026_08_28_000402_5797_create_fuel_pumps_tanks_meters_tables';

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
        foreach (['fuel_products', 'fuel_pumps', 'fuel_tanks', 'fuel_meters', 'fuel_equipment'] as $table) {
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

        FuelPump::query()->create(['code' => 'P-1', 'name' => 'Sans tenant']);
    }

    public function test_tank_capacity_must_be_positive(): void
    {
        try {
            FuelTank::query()->create([
                'company_id' => $this->companyA->id,
                'code' => 'TK-0',
                'name' => 'Cuve vide',
                'capacity' => 0,
            ]);
            $this->fail('Le CHECK fuel_tanks_capacity_check aurait dû rejeter une capacité nulle.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_tanks_capacity_check', $exception->getMessage());
        }
    }

    public function test_tank_unit_is_allowlisted(): void
    {
        try {
            FuelTank::query()->create([
                'company_id' => $this->companyA->id,
                'code' => 'TK-1',
                'name' => 'Cuve',
                'capacity' => 5000,
                'unit' => 'barrel',
            ]);
            $this->fail('Le CHECK fuel_tanks_unit_check aurait dû rejeter l\'unité.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_tanks_unit_check', $exception->getMessage());
        }
    }

    public function test_only_one_active_meter_per_pump(): void
    {
        /** @var FuelPump $pump */
        $pump = FuelPump::query()->create([
            'company_id' => $this->companyA->id,
            'code' => 'P-1',
            'name' => 'Pompe 1',
        ]);

        FuelMeter::query()->create([
            'company_id' => $this->companyA->id,
            'pump_id' => $pump->id,
            'code' => 'M-1',
            'name' => 'Compteur 1',
        ]);

        // Second compteur actif sur la même pompe → index partiel UNIQUE.
        try {
            FuelMeter::query()->create([
                'company_id' => $this->companyA->id,
                'pump_id' => $pump->id,
                'code' => 'M-2',
                'name' => 'Compteur 2',
            ]);
            $this->fail('L\'index fuel_meters_active_per_pump_unique aurait dû rejeter un 2e compteur actif.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_meters_active_per_pump_unique', $exception->getMessage());
        }

        // Un compteur inactif est autorisé en plus.
        FuelMeter::query()->create([
            'company_id' => $this->companyA->id,
            'pump_id' => $pump->id,
            'code' => 'M-2',
            'name' => 'Compteur remplacé',
            'is_active' => false,
        ]);

        $this->assertSame(2, FuelMeter::query()->count());
    }

    public function test_pump_status_is_allowlisted(): void
    {
        try {
            FuelPump::query()->create([
                'company_id' => $this->companyA->id,
                'code' => 'P-X',
                'name' => 'Pompe',
                'status' => 'melted',
            ]);
            $this->fail('Le CHECK fuel_pumps_status_check aurait dû rejeter le statut.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_pumps_status_check', $exception->getMessage());
        }
    }

    public function test_cross_tenant_site_reference_is_rejected_when_sites_exist(): void
    {
        if (! Schema::hasTable('fuel_sites')) {
            $this->markTestSkipped('dépendance #5796 (fuel_sites) non mergée — FK composite non matérialisée');
        }

        $siteA = DB::table('fuel_sites')->insertGetId([
            'company_id' => $this->companyA->id,
            'code' => 'SITE-A',
            'name' => 'Site A',
        ]);

        try {
            DB::table('fuel_pumps')->insert([
                'company_id' => $this->companyB->id,
                'site_id' => $siteA,
                'code' => 'P-X',
                'name' => 'Pompe cross-tenant',
            ]);
            $this->fail('La FK composite fuel_pumps_site_company_fk aurait dû rejeter la référence cross-tenant.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_pumps_site_company_fk', $exception->getMessage());
        }
    }

    public function test_migration_is_idempotent_and_rollback_works(): void
    {
        $migration = require database_path('migrations/tenant/'.self::MIGRATION.'.php');

        $migration->up();
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('fuel_meters'));

        $migration->down();
        foreach (['fuel_products', 'fuel_pumps', 'fuel_tanks', 'fuel_meters', 'fuel_equipment'] as $table) {
            $this->assertFalse(DB::getSchemaBuilder()->hasTable($table), "table {$table} encore présente après down()");
        }
    }
}
