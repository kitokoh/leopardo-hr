<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcDriverPosition;
use App\Modules\Vtc\Domain\Models\VtcFareProfile;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Domain\Models\VtcRideEvent;
use App\Modules\Vtc\Domain\Models\VtcVehicle;
use App\Modules\Vtc\Domain\Enums\VtcRideEventType;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * VTC-02 (#8358, BC-34 VTC) — modèles Eloquent du domaine : casts enums,
 * relations, scope tenant (un tenant ne voit jamais les données d'un
 * autre), GeoLocatable du chauffeur (contrat du core geo BC-33).
 */
class VtcModelsTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    public function test_driver_vehicle_ride_relations_and_enum_casts(): void
    {
        $company = $this->createCompany();

        $this->withTenantContext($company, function (): void {
            $vehicle = VtcVehicle::factory()->create(['plate' => 'LT-001-AA']);
            $driver = VtcDriver::factory()->create([
                'vehicle_id' => $vehicle->id,
                'status' => VtcDriverStatus::Available->value,
            ]);
            $fareProfile = VtcFareProfile::factory()->create(['is_default' => true]);
            $ride = VtcRide::factory()->create([
                'driver_id' => $driver->id,
                'fare_profile_id' => $fareProfile->id,
                'status' => VtcRideStatus::Accepted->value,
            ]);

            VtcRideEvent::create([
                'ride_id' => $ride->id,
                'type' => VtcRideEventType::RideAccepted->value,
                'payload' => ['driver_id' => $driver->id],
            ]);

            $freshRide = VtcRide::query()->findOrFail($ride->id);

            self::assertSame(VtcRideStatus::Accepted, $freshRide->status);
            self::assertSame($driver->id, $freshRide->driver->id);
            self::assertSame($fareProfile->id, $freshRide->fareProfile->id);
            self::assertCount(1, $freshRide->events);
            self::assertSame(VtcRideEventType::RideAccepted, $freshRide->events->first()->type);

            $freshDriver = VtcDriver::query()->findOrFail($driver->id);

            self::assertSame(VtcDriverStatus::Available, $freshDriver->status);
            self::assertSame($vehicle->id, $freshDriver->vehicle->id);
            self::assertCount(1, $freshDriver->rides);
        });
    }

    public function test_models_never_leak_across_tenants(): void
    {
        $companyA = $this->createCompany();
        $companyB = $this->createCompany();

        $this->withTenantContext($companyA, function (): void {
            VtcDriver::factory()->create(['name' => 'Chauffeur A']);
            VtcRide::factory()->create(['passenger_name' => 'Passager A']);
        });

        $this->withTenantContext($companyB, function (): void {
            VtcDriver::factory()->create(['name' => 'Chauffeur B']);
        });

        $this->withTenantContext($companyA, function (): void {
            self::assertSame(['Chauffeur A'], VtcDriver::query()->pluck('name')->all());
            self::assertSame(['Passager A'], VtcRide::query()->pluck('passenger_name')->all());
        });
    }

    public function test_driver_is_geo_locatable_for_the_geo_core(): void
    {
        $company = $this->createCompany();

        $this->withTenantContext($company, function (): void {
            $driver = VtcDriver::factory()->availableAt(4.0511, 9.7679)->create(['name' => 'Geo Driver']);

            self::assertSame('Geo Driver', $driver->geoLabel());
            self::assertSame('current_latitude', VtcDriver::geoLatitudeColumn());
            self::assertSame('current_longitude', VtcDriver::geoLongitudeColumn());

            $point = $driver->geoPoint();

            self::assertNotNull($point);
            self::assertSame(4.0511, $point->latitude);
            self::assertSame(9.7679, $point->longitude);

            // Sans position connue : geoPoint() null (exclu du nearest).
            $offline = VtcDriver::factory()->create();

            self::assertNull($offline->geoPoint());
        });
    }

    public function test_driver_position_is_idempotent_by_unique_index(): void
    {
        $company = $this->createCompany();

        $this->withTenantContext($company, function (): void {
            $driver = VtcDriver::factory()->availableAt(4.0511, 9.7679)->create();

            VtcDriverPosition::create([
                'driver_id' => $driver->id,
                'latitude' => 4.0511,
                'longitude' => 9.7679,
                'recorded_at' => '2026-10-10 12:00:00',
                'source' => 'app',
            ]);

            // Même (driver_id, recorded_at) : la contrainte unique rejette le
            // doublon (ingestion idempotente, VTC-05).
            $this->expectException(\Illuminate\Database\QueryException::class);

            VtcDriverPosition::create([
                'driver_id' => $driver->id,
                'latitude' => 4.0520,
                'longitude' => 9.7680,
                'recorded_at' => '2026-10-10 12:00:00',
                'source' => 'app',
            ]);
        });
    }

    private function createCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        return $company;
    }
}
