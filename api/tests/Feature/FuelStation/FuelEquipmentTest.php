<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5797 (FUEL-003) — pompes, cuves et compteurs : FK composites
 * anti cross-tenant, compteur actif unique par (pompe, code), capacités
 * et unités strictement validées.
 */
class FuelEquipmentTest extends TestCase
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

    private function pump(Company $company, FuelStation $station, string $code = 'P-01'): FuelPump
    {
        /** @var FuelPump $pump */
        $pump = FuelPump::query()->create([
            'company_id' => $company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'code' => $code,
            'product_types' => ['essence', 'gazoil'],
            'status' => FuelPump::STATUS_ACTIVE,
        ]);

        return $pump;
    }

    private function tank(Company $company, FuelStation $station, string $code = 'T-01'): FuelTank
    {
        /** @var FuelTank $tank */
        $tank = FuelTank::query()->create([
            'company_id' => $company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'code' => $code,
            'product_type' => 'essence',
            'capacity_minor' => 5000000,
            'current_level_minor' => 2500000,
            'status' => FuelTank::STATUS_ACTIVE,
        ]);

        return $tank;
    }

    private function meter(Company $company, FuelStation $station, FuelPump $pump, string $code = 'C-04-A'): FuelMeterRegister
    {
        /** @var FuelMeterRegister $meter */
        $meter = FuelMeterRegister::query()->create([
            'company_id' => $company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'pump_id' => (int) $pump->getAttribute('id'),
            'meter_code' => $code,
            'meter_type' => FuelMeterRegister::TYPE_ELECTRONIC,
            'product_code' => 'essence',
            'unit_code' => 'l',
            'precision_scale' => 2,
            'status' => FuelMeterRegister::STATUS_ACTIVE,
        ]);

        return $meter;
    }

    public function test_pump_cannot_link_to_another_tenant_station(): void
    {
        $foreignStation = $this->station($this->companyB);

        // FK composite (station_id, company_id) → refus cross-tenant.
        $this->expectException(QueryException::class);
        DB::transaction(function () use ($foreignStation): void {
            FuelPump::query()->create([
                'company_id' => $this->companyA->id,
                'station_id' => (int) $foreignStation->getAttribute('id'),
                'code' => 'P-X',
                'status' => FuelPump::STATUS_ACTIVE,
            ]);
        });
    }

    public function test_tank_cannot_link_to_another_tenant_station(): void
    {
        $foreignStation = $this->station($this->companyB);

        $this->expectException(QueryException::class);
        DB::transaction(function () use ($foreignStation): void {
            FuelTank::query()->create([
                'company_id' => $this->companyA->id,
                'station_id' => (int) $foreignStation->getAttribute('id'),
                'code' => 'T-X',
                'product_type' => 'gazoil',
                'capacity_minor' => 1000,
                'current_level_minor' => 0,
                'status' => FuelTank::STATUS_ACTIVE,
            ]);
        });
    }

    public function test_only_one_active_meter_per_pump_and_code(): void
    {
        $station = $this->station($this->companyA);
        $pump = $this->pump($this->companyA, $station);
        $this->meter($this->companyA, $station, $pump, 'C-04-A');

        // Second compteur ACTIF avec le même code → contrainte unique.
        $this->expectException(QueryException::class);
        DB::transaction(function () use ($station, $pump): void {
            $this->meter($this->companyA, $station, $pump, 'C-04-A');
        });
    }

    public function test_two_active_meters_with_different_codes_are_allowed(): void
    {
        $station = $this->station($this->companyA);
        $pump = $this->pump($this->companyA, $station);
        $this->meter($this->companyA, $station, $pump, 'C-04-A');
        $this->meter($this->companyA, $station, $pump, 'C-04-B');

        $this->assertSame(2, FuelMeterRegister::query()->count());
    }

    public function test_meter_type_and_status_are_strictly_validated(): void
    {
        $station = $this->station($this->companyA);
        $pump = $this->pump($this->companyA, $station);

        $this->expectException(QueryException::class);
        DB::transaction(function () use ($station, $pump): void {
            FuelMeterRegister::query()->create([
                'company_id' => $this->companyA->id,
                'station_id' => (int) $station->getAttribute('id'),
                'pump_id' => (int) $pump->getAttribute('id'),
                'meter_code' => 'C-BAD',
                'meter_type' => 'quantum',
                'status' => FuelMeterRegister::STATUS_ACTIVE,
            ]);
        });
    }

    public function test_capacity_is_positive_integer_minor_units(): void
    {
        $station = $this->station($this->companyA);

        $this->expectException(QueryException::class);
        DB::transaction(function () use ($station): void {
            FuelTank::query()->create([
                'company_id' => $this->companyA->id,
                'station_id' => (int) $station->getAttribute('id'),
                'code' => 'T-NEG',
                'product_type' => 'essence',
                'capacity_minor' => -500, // jamais négatif
                'current_level_minor' => 0,
                'status' => FuelTank::STATUS_ACTIVE,
            ]);
        });
    }
}
