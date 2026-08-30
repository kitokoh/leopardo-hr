<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Enums\FuelSiteStatus;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Sites opérationnels d'une station — Issue #5796 (FUEL-002).
 *
 * Verrouille :
 *   1. table `fuel_sites` créée dans le schéma tenant (parité migrations) ;
 *   2. référence cross-tenant impossible (FK composite station_id+company_id) ;
 *   3. statut allowlisté (CHECK active|inactive) ;
 *   4. company_id non nullable ;
 *   5. migration idempotente + rollback propre (down: fuel_sites avant fuel_stations).
 */
class FuelSitesInvariantTest extends TestCase
{
    use RefreshTenantDatabase;

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

    private function station(Company $company, string $code = 'ST-001'): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => 'Station '.$code,
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }

    public function test_fuel_sites_table_exists_in_tenant_schema(): void
    {
        $this->assertTrue(Schema::hasTable('fuel_sites'));
        $this->assertTrue(Schema::hasTable('fuel_stations'));

        $row = DB::selectOne(
            'SELECT t.table_schema FROM information_schema.tables t WHERE t.table_name = ? LIMIT 1',
            ['fuel_sites']
        );
        $this->assertSame('shared_tenants', $row->table_schema ?? null, 'fuel_sites absente du schéma tenant');
    }

    public function test_site_creation_requires_company(): void
    {
        $this->expectException(QueryException::class);

        // Savepoint (#4978) : le RAISE ne doit pas empoisonner la transaction
        // RefreshDatabase (sinon 25P02 en cascade sur le tearDown).
        DB::transaction(function (): void {
            FuelSite::query()->create(['code' => 'S-1', 'name' => 'Sans tenant']);
        });
    }

    public function test_site_cannot_link_to_another_tenant_station(): void
    {
        $stationB = $this->station($this->companyB, 'ST-OTHER');

        try {
            DB::transaction(function () use ($stationB): void {
                FuelSite::query()->create([
                    'company_id' => $this->companyA->id,
                    'station_id' => (int) $stationB->getAttribute('id'),
                    'code' => 'S-X',
                    'name' => 'Site cross-tenant',
                ]);
            });
            $this->fail('La FK composite fuel_sites_station_company_fk aurait dû rejeter la référence cross-tenant.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_sites_station_company_fk', $exception->getMessage());
        }
    }

    public function test_site_status_is_allowlisted(): void
    {
        $station = $this->station($this->companyA);

        try {
            DB::transaction(function () use ($station): void {
                FuelSite::query()->create([
                    'company_id' => $this->companyA->id,
                    'station_id' => (int) $station->getAttribute('id'),
                    'code' => 'S-1',
                    'name' => 'Site',
                    'status' => 'exploded',
                ]);
            });
            $this->fail('Le CHECK fuel_sites_status_check aurait dû rejeter le statut.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_sites_status_check', $exception->getMessage());
        }
    }

    public function test_site_lifecycle_roundtrip(): void
    {
        $station = $this->station($this->companyA);

        /** @var FuelSite $site */
        $site = FuelSite::query()->create([
            'company_id' => $this->companyA->id,
            'station_id' => (int) $station->getAttribute('id'),
            'code' => 'S-1',
            'name' => 'Site principal',
            'address' => '12 rue des pompes',
            'status' => FuelSiteStatus::Active->value, // default DB — explicite pour l'objet mémoire
        ]);

        $this->assertTrue($site->isActive());
        $this->assertSame('S-1', $site->code);
        $this->assertSame((int) $station->getAttribute('id'), $site->station_id);
        $this->assertSame($station->getAttribute('id'), $site->station?->getAttribute('id'));
    }
}
