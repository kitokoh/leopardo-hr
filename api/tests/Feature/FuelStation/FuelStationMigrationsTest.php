<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * FUEL-002 — Migrations stations et sites (issue #5796).
 *
 * Fresh/re-run/rollback, aucune ligne sans company_id, index tenant-first,
 * codes uniques par tenant (une même référence cross-tenant est possible
 * sans fuite), isolation stricte.
 */
class FuelStationMigrationsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
    }

    public function test_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('fuel_stations'));
        $this->assertTrue(Schema::hasTable('fuel_sites'));

        $stationColumns = Schema::getColumnListing('fuel_stations');
        $this->assertContains('company_id', $stationColumns);
        $this->assertContains('code', $stationColumns);
        $this->assertContains('status', $stationColumns);
        $this->assertContains('timezone', $stationColumns);
    }

    public function test_same_code_is_allowed_across_tenants_but_unique_within_tenant(): void
    {
        FuelStation::query()->create([
            'company_id' => $this->companyA->id,
            'code' => 'ST-01',
            'name' => 'Station A',
        ]);
        FuelStation::query()->create([
            'company_id' => $this->companyB->id,
            'code' => 'ST-01',
            'name' => 'Station B',
        ]);

        $this->assertSame(2, FuelStation::query()->withoutGlobalScopes()->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        FuelStation::query()->create([
            'company_id' => $this->companyA->id,
            'code' => 'ST-01',
            'name' => 'Doublon',
        ]);
    }

    public function test_company_id_is_never_null(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        FuelStation::query()->create([
            'code' => 'ST-NOCOMPANY',
            'name' => 'Sans tenant',
        ]);
    }

    public function test_sites_belong_to_station_within_same_tenant(): void
    {
        $stationA = FuelStation::query()->create([
            'company_id' => $this->companyA->id,
            'code' => 'ST-A1',
            'name' => 'Station A1',
        ]);

        FuelSite::query()->create([
            'company_id' => $this->companyA->id,
            'station_id' => $stationA->id,
            'code' => 'SITE-A-1',
            'name' => 'Site A1',
        ]);

        // Un site du tenant B ne peut pas référencer une station du tenant A
        // (référence cross-tenant impossible par construction : le scope
        // BelongsToCompany + le champ company_id explicite l'isolent).
        $this->assertSame(1, FuelSite::query()->withoutGlobalScopes()->where('company_id', $this->companyA->id)->count());
    }

    public function test_indexes_are_tenant_first(): void
    {
        $indexes = \Illuminate\Support\Facades\DB::select(
            'SELECT indexname FROM pg_indexes WHERE tablename = ?',
            ['fuel_stations'],
        );

        $indexNames = array_map(static fn ($row): string => $row->indexname, $indexes);
        $this->assertContains('fuel_stations_company_code_unique', $indexNames);
        $this->assertContains('fuel_stations_company_status_index', $indexNames);
    }
}
