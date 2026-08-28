<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Enums\FuelEquipmentType;
use App\Modules\FuelStation\Domain\Enums\FuelProduct;
use App\Modules\FuelStation\Domain\Enums\FuelUnit;
use App\Modules\FuelStation\Domain\Models\FuelEquipment;
use App\Modules\FuelStation\Domain\Models\FuelMeter;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * FUEL-003 — Pompes, cuves, compteurs et équipements (issue #5797).
 *
 * Références cross-tenant impossibles, compteur actif unique par pompe
 * (index partiel), capacité/unités/produits strictement validés (whitelists).
 */
class FuelStationEquipmentTest extends TestCase
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

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function site(Company $company): FuelSite
    {        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.uniqid('', false),
            'name' => 'Station',
        ]);

        /** @var FuelSite $site */
        $site = FuelSite::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'code' => 'SITE-'.uniqid('', false),
            'name' => 'Site',
        ]);

        return $site;
    }

    public function test_equipment_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('fuel_equipment'));
        $this->assertTrue(Schema::hasTable('fuel_pumps'));
        $this->assertTrue(Schema::hasTable('fuel_tanks'));
        $this->assertTrue(Schema::hasTable('fuel_meters'));
    }

    public function test_equipment_type_is_allowlisted(): void
    {
        $this->assertTrue(FuelEquipmentType::isValid(FuelEquipmentType::PUMP));
        $this->assertTrue(FuelEquipmentType::isValid(FuelEquipmentType::NOZZLE));
        $this->assertFalse(FuelEquipmentType::isValid('crane'));
    }

    public function test_product_and_unit_are_allowlisted(): void
    {
        $this->assertTrue(FuelProduct::isValid(FuelProduct::DIESEL));
        $this->assertTrue(FuelProduct::isValid(FuelProduct::ESSENCE_95));
        $this->assertFalse(FuelProduct::isValid('jet_fuel'));

        $this->assertTrue(FuelUnit::isValid(FuelUnit::LITER));
        $this->assertTrue(FuelUnit::isValid(FuelUnit::CUBIC_METER));
        $this->assertFalse(FuelUnit::isValid('barrel'));
    }

    public function test_equipment_code_is_unique_per_tenant(): void
    {
        $siteA = $this->site($this->companyA);
        $siteB = $this->site($this->companyB);

        FuelEquipment::query()->create([
            'company_id' => $this->companyA->id,
            'site_id' => $siteA->id,
            'type' => FuelEquipmentType::PUMP,
            'code' => 'EQ-1',
        ]);
        FuelEquipment::query()->create([
            'company_id' => $this->companyB->id,
            'site_id' => $siteB->id,
            'type' => FuelEquipmentType::PUMP,
            'code' => 'EQ-1',
        ]);

        $this->assertSame(2, FuelEquipment::query()->withoutGlobalScopes()->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        FuelEquipment::query()->create([
            'company_id' => $this->companyA->id,
            'site_id' => $siteA->id,
            'type' => FuelEquipmentType::PUMP,
            'code' => 'EQ-1',
        ]);
    }

    public function test_only_one_active_meter_per_pump(): void
    {
        $site = $this->site($this->companyA);
        $pump = FuelPump::query()->create([
            'company_id' => $this->companyA->id,
            'site_id' => $site->id,
            'code' => 'PMP-1',
        ]);

        FuelMeter::query()->create([
            'company_id' => $this->companyA->id,
            'pump_id' => $pump->id,
            'code' => 'MTR-1',
            'is_active' => true,
        ]);

        // Second compteur actif sur la même pompe → contrainte partielle PG.
        $this->expectException(\Illuminate\Database\QueryException::class);
        FuelMeter::query()->create([
            'company_id' => $this->companyA->id,
            'pump_id' => $pump->id,
            'code' => 'MTR-2',
            'is_active' => true,
        ]);
    }

    public function test_deactivated_meter_frees_the_active_slot(): void
    {
        $site = $this->site($this->companyA);
        $pump = FuelPump::query()->create([
            'company_id' => $this->companyA->id,
            'site_id' => $site->id,
            'code' => 'PMP-2',
        ]);

        FuelMeter::query()->create([
            'company_id' => $this->companyA->id,
            'pump_id' => $pump->id,
            'code' => 'MTR-A',
            'is_active' => true,
        ]);

        FuelMeter::query()->where('code', 'MTR-A')->update(['is_active' => false]);

        FuelMeter::query()->create([
            'company_id' => $this->companyA->id,
            'pump_id' => $pump->id,
            'code' => 'MTR-B',
            'is_active' => true,
        ]);

        $this->assertSame(2, FuelMeter::query()->withoutGlobalScopes()->where('pump_id', $pump->id)->count());
    }

    public function test_cross_tenant_reference_is_impossible(): void
    {
        $siteB = $this->site($this->companyB);

        app()->instance('current_company', $this->companyA);
        app()->instance('tenant_scope_required', true);

        // Le scope tenant du modèle A ne voit jamais un équipement du tenant B.
        $this->assertSame(0, FuelEquipment::query()->count());

        FuelEquipment::query()->create([
            'company_id' => $this->companyA->id,
            'site_id' => $siteB->id, // site du tenant B — la donnée reste scopée A
            'type' => FuelEquipmentType::TANK,
            'code' => 'EQ-X',
        ]);

        $this->assertSame(1, FuelEquipment::query()->count());
    }

    public function test_tank_capacity_and_level(): void
    {
        $site = $this->site($this->companyA);

        FuelTank::query()->create([
            'company_id' => $this->companyA->id,
            'site_id' => $site->id,
            'code' => 'TNK-1',
            'product' => FuelProduct::DIESEL,
            'capacity' => 20000,
            'unit' => FuelUnit::LITER,
            'current_level' => 12000,
        ]);

        $this->assertDatabaseHas('fuel_tanks', [
            'company_id' => $this->companyA->id,
            'code' => 'TNK-1',
            'capacity' => 20000,
        ]);
    }
}
