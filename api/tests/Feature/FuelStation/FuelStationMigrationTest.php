<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5796 (FUEL-002) — migrations stations : contraintes, index
 * tenant-first, unicité du code par tenant, CHECK statut, fresh/re-run.
 */
class FuelStationMigrationTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function station(Company $company, array $overrides = []): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create(array_merge([
            'company_id' => $company->id,
            'code' => 'ST-001',
            'name' => 'Station Centre',
            'address' => '12 rue des Palmiers',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ], $overrides));

        return $station;
    }

    public function test_station_is_created_with_company_id_not_null(): void
    {
        $station = $this->station($this->companyA);

        $this->assertSame($this->companyA->id, $station->company_id);
        $this->assertSame('ST-001', $station->code);

        // Aucune ligne sans company_id (NOT NULL imposé par le schéma).
        $count = DB::table('fuel_stations')->whereNull('company_id')->count();
        $this->assertSame(0, $count);
    }

    public function test_code_is_unique_per_tenant_only(): void
    {
        $this->station($this->companyA, ['code' => 'ST-001']);

        // Même code chez un AUTRE tenant → autorisé (isolation).
        $this->station($this->companyB, ['code' => 'ST-001']);

        // Même code chez le MÊME tenant → contrainte unique. La transaction
        // imbriquée crée un savepoint (pattern #4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase du test (25P02).
        $this->expectException(QueryException::class);
        DB::transaction(function (): void {
            $this->station($this->companyA, ['code' => 'ST-001']);
        });
    }

    public function test_status_check_rejects_unknown_status(): void
    {
        $this->expectException(QueryException::class);
        DB::transaction(function (): void {
            $this->station($this->companyA, ['status' => 'exploded']);
        });
    }

    public function test_valid_statuses_are_accepted(): void
    {
        $this->station($this->companyA, ['code' => 'A1', 'status' => 'active']);
        $this->station($this->companyA, ['code' => 'A2', 'status' => 'inactive']);
        $this->station($this->companyA, ['code' => 'A3', 'status' => 'archived']);

        $this->assertSame(3, FuelStation::query()->count());
    }

    public function test_indexes_are_tenant_first(): void
    {
        $indexes = DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'fuel_stations'"
        );
        $names = array_map(static fn ($row): string => (string) $row->indexname, $indexes);

        // Index composés démarrant par company_id (tenant-first).
        $this->assertContains('fuel_stations_company_code_unique', $names);
        $this->assertContains('fuel_stations_company_status_idx', $names);
    }

    public function test_global_scope_scopes_to_current_tenant(): void
    {
        $this->station($this->companyA);
        $this->station($this->companyB);

        // Contexte tenant A → seule la station A est visible.
        app()->instance('current_company', $this->companyA);
        app()->instance('tenant_scope_required', true);

        $this->assertSame(1, FuelStation::query()->count());

        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');
    }
}
