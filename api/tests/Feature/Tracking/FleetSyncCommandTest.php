<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Fleet\Domain\Models\VehiclePosition;
use App\Modules\Fleet\Domain\Models\VehicleTrip;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7401 — `leopardo:fleet:sync` : la synchronisation Traccar de la
 * flotte devait être **planifiée** (elle ne l'était pas) et `sync-positions`
 * devait **persister** quelque chose (il ne le faisait pas).
 *
 * Le test couvre ce qui est écrit réellement (appairage des devices,
 * positions GPS dans `vehicle_positions`, trajets convertis en itinéraires),
 * l'**idempotence** d'un rejeu, le **bornage** de la fenêtre (`--days`, plafond
 * 90 jours) et de la passe (`--limit`), et l'**isolation tenant** (un tenant
 * n'écrit jamais chez un autre). Il vérifie aussi que l'endpoint historique
 * `POST /tracking/sync-positions` persiste désormais au lieu de compter.
 */
class FleetSyncCommandTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tracking.traccar_url' => 'https://traccar.test/',
            'tracking.traccar_token' => 'secret-token',
        ]);
    }

    /** @test */
    public function it_links_devices_writes_positions_and_trips_then_is_idempotent(): void
    {
        $company = $this->company();
        $vehicle = $this->vehicle($company, ['traccar_unique_id' => 'IMEI-77']);

        $this->fakeTraccar();

        $this->artisan('leopardo:fleet:sync')->assertSuccessful();

        // 1. Appairage par uniqueId : le véhicule reçoit l'id d'appareil Traccar.
        self::assertSame(77, (int) $vehicle->fresh()?->traccar_device_id);

        // 2. Positions persistées (le constat #2 de l'issue : avant, rien
        //    n'était écrit), avec la vitesse convertie des nœuds vers km/h.
        self::assertSame(2, VehiclePosition::query()->count());

        $position = VehiclePosition::query()->where('traccar_position_id', 1001)->firstOrFail();
        self::assertSame($vehicle->id, $position->vehicle_id);
        self::assertSame((int) 77, (int) $position->device_id);
        self::assertEqualsWithDelta(4.0510564, (float) $position->latitude, 0.0000001);
        self::assertEqualsWithDelta(9.7678687, (float) $position->longitude, 0.0000001);
        self::assertSame('48.15', (string) $position->speed_kmh);
        self::assertSame('2026-09-12 06:00:00', $position->recorded_at?->utc()->format('Y-m-d H:i:s'));

        // 3. Trajets convertis : mètres → km, millisecondes → minutes,
        //    nœuds → km/h (les valeurs de l'issue #7399).
        self::assertSame(1, VehicleTrip::query()->count());

        $trip = VehicleTrip::query()->firstOrFail();
        self::assertSame(9001, (int) $trip->traccar_trip_id);
        self::assertSame('250.00', (string) $trip->distance_km);
        self::assertSame(135, (int) $trip->duration_minutes);
        self::assertSame('76.86', (string) $trip->max_speed_kmh);
        self::assertSame('55.93', (string) $trip->avg_speed_kmh);
        self::assertSame('Douala, Wouri, Littoral, Cameroun', $trip->start_address);
        self::assertSame('Edéa, Sanaga-Maritime, Littoral, Cameroun', $trip->end_address);

        // 4. Rejeu de la même fenêtre : aucun doublon (idempotence).
        $this->artisan('leopardo:fleet:sync')->assertSuccessful();

        self::assertSame(2, VehiclePosition::query()->count());
        self::assertSame(1, VehicleTrip::query()->count());
    }

    /** @test */
    public function it_bounds_the_window_to_90_days_and_the_pass_by_limit(): void
    {
        $company = $this->company();
        $first = $this->vehicle($company, ['traccar_unique_id' => 'IMEI-77', 'traccar_device_id' => 77]);
        $second = $this->vehicle($company, ['traccar_unique_id' => 'IMEI-78', 'traccar_device_id' => 78]);

        $this->fakeTraccar();

        // --days=999 doit être ramené au plafond de 90 jours ; --limit=1 ne
        // traite qu'un seul véhicule (le premier par id) : le fake Traccar
        // renvoie deux positions, toutes rattachées au véhicule traité.
        $this->artisan('leopardo:fleet:sync', ['--days' => 999, '--limit' => 1])->assertSuccessful();

        self::assertSame(2, VehiclePosition::query()->count());
        $positioned = VehiclePosition::query()->orderBy('id')->firstOrFail();
        self::assertSame($first->id, $positioned->vehicle_id);
        self::assertSame(0, VehiclePosition::query()->where('vehicle_id', $second->id)->count());
        self::assertSame(0, VehicleTrip::query()->where('vehicle_id', $second->id)->count());

        $from = $this->recordedQuery('https://traccar.test/api/reports/trips', 'from');

        self::assertNotNull($from, 'Aucune requête de trajets enregistrée.');
        self::assertEqualsWithDelta(90, Carbon::parse($from)->diffInDays(now()), 1);
    }

    /** @test */
    public function it_never_writes_for_another_tenant(): void
    {
        $company = $this->company();
        $other = $this->company();

        $mine = $this->vehicle($company, ['traccar_unique_id' => 'IMEI-77', 'traccar_device_id' => 77]);
        $theirs = $this->vehicle($other, ['traccar_unique_id' => 'IMEI-78', 'traccar_device_id' => 78]);

        $this->fakeTraccar();

        $this->artisan('leopardo:fleet:sync', ['--tenant' => $company->id])->assertSuccessful();

        self::assertSame(2, VehiclePosition::query()->where('vehicle_id', $mine->id)->count());
        self::assertSame(0, VehiclePosition::query()->where('vehicle_id', $theirs->id)->count());
        self::assertSame(1, VehicleTrip::query()->where('vehicle_id', $mine->id)->count());
        self::assertSame(0, VehicleTrip::query()->where('vehicle_id', $theirs->id)->count());
    }

    /** @test */
    public function it_is_a_fail_open_noop_when_traccar_is_not_configured(): void
    {
        config(['tracking.traccar_token' => null]);

        $company = $this->company();
        $this->vehicle($company, ['traccar_unique_id' => 'IMEI-77']);

        Http::fake();

        $this->artisan('leopardo:fleet:sync')->assertSuccessful();

        self::assertSame(0, VehiclePosition::query()->count());
        self::assertSame(0, VehicleTrip::query()->count());
    }

    /** @test */
    public function the_manual_sync_positions_endpoint_now_persists_positions(): void
    {
        $company = $this->company();
        $vehicle = $this->vehicle($company, ['traccar_unique_id' => 'IMEI-77', 'traccar_device_id' => 77]);

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Http::fake([
            'https://traccar.test/api/positions*' => Http::response([
                [
                    'id' => 1001,
                    'deviceId' => 77,
                    'fixTime' => '2026-09-12T06:00:00Z',
                    'latitude' => 4.0510564,
                    'longitude' => 9.7678687,
                    'speed' => 26.0,
                ],
            ], 200),
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/tracking/sync-positions')
            ->assertOk()
            ->assertJsonPath('total_tracked', 1)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('positions_written', 1);

        self::assertSame(1, VehiclePosition::query()->where('vehicle_id', $vehicle->id)->count());

        // Un second appel n'écrit pas de doublon (même id de position Traccar).
        $this->postJson('/api/v1/tracking/sync-positions')
            ->assertOk()
            ->assertJsonPath('positions_written', 0);

        self::assertSame(1, VehiclePosition::query()->count());
    }

    /**
     * Fake Traccar conforme (devices / positions / reports/trips) — les valeurs
     * sont celles de la recette de l'issue : 250 km, 135 min, 76,86 km/h.
     */
    private function fakeTraccar(): void
    {
        Http::fake([
            'https://traccar.test/api/devices' => Http::response([
                ['id' => 77, 'uniqueId' => 'IMEI-77'],
                ['id' => 78, 'uniqueId' => 'IMEI-78'],
            ], 200),
            'https://traccar.test/api/positions*' => Http::response([
                [
                    'id' => 1001,
                    'deviceId' => 77,
                    'fixTime' => '2026-09-12T06:00:00Z',
                    'latitude' => 4.0510564,
                    'longitude' => 9.7678687,
                    'speed' => 26.0,
                ],
                [
                    'id' => 1002,
                    'deviceId' => 77,
                    'fixTime' => '2026-09-12T06:05:00Z',
                    'latitude' => 4.06,
                    'longitude' => 9.77,
                    'speed' => 30.0,
                ],
            ], 200),
            'https://traccar.test/api/reports/trips*' => Http::response([
                [
                    'id' => 9001,
                    'deviceId' => 77,
                    'startTime' => '2026-09-12T06:00:00Z',
                    'endTime' => '2026-09-12T08:15:00Z',
                    'startLat' => 4.0510564,
                    'startLon' => 9.7678687,
                    'startAddress' => 'Douala, Wouri, Littoral, Cameroun',
                    'endLat' => 3.8,
                    'endLon' => 10.1333,
                    'endAddress' => 'Edéa, Sanaga-Maritime, Littoral, Cameroun',
                    'distance' => 250000,
                    'duration' => 8100000,
                    'maxSpeed' => 41.5,
                    'averageSpeed' => 30.2,
                ],
            ], 200),
        ]);
    }

    private function recordedQuery(string $urlPrefix, string $key): ?string
    {
        foreach (Http::recorded() as [$request, $response]) {
            /** @var Request $request */
            if (! str_starts_with($request->url(), $urlPrefix)) {
                continue;
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            $value = $query[$key] ?? null;

            return is_string($value) ? $value : null;
        }

        return null;
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        return $company;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function vehicle(Company $company, array $overrides = []): Vehicle
    {
        return Vehicle::query()->create(array_merge([
            'company_id' => $company->id,
            'plate_number' => 'DZ-7401-'.fake()->unique()->bothify('####'),
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'type' => 'van',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ], $overrides));
    }
}
