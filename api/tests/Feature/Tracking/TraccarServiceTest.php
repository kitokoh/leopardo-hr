<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Services\Tracking\TraccarService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TraccarServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tracking.traccar_url' => 'https://traccar.test/',
            'tracking.traccar_token' => 'secret-token',
        ]);
    }

    public function test_device_crud_and_permissions_call_traccar_with_bearer_token(): void
    {
        Http::fake([
            'https://traccar.test/api/devices' => Http::sequence()
                ->push([['id' => 10, 'name' => 'Truck 10']], 200)
                ->push(['id' => 11, 'name' => 'Truck 11'], 200),
            'https://traccar.test/api/devices/11' => Http::sequence()
                ->push(['id' => 11, 'name' => 'Truck 11 updated'], 200)
                ->push(null, 204),
            'https://traccar.test/api/geofences' => Http::sequence()
                ->push([['id' => 3, 'name' => 'Depot']], 200)
                ->push(['id' => 4, 'name' => 'Warehouse'], 200),
            'https://traccar.test/api/permissions' => Http::response(['linked' => true], 200),
        ]);

        $service = new TraccarService;

        self::assertSame([['id' => 10, 'name' => 'Truck 10']], $service->getDevices());
        self::assertSame(['id' => 11, 'name' => 'Truck 11'], $service->createDevice('Truck 11', 'IMEI-11'));
        self::assertSame(['id' => 11, 'name' => 'Truck 11 updated'], $service->updateDevice(11, ['name' => 'Truck 11 updated']));
        $service->deleteDevice(11);
        self::assertSame([['id' => 3, 'name' => 'Depot']], $service->getGeofences());
        self::assertSame(['id' => 4, 'name' => 'Warehouse'], $service->createGeofence('Warehouse', 'CIRCLE(1 2, 100)'));
        $service->linkGeofenceToDevice(4, 11);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer secret-token'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://traccar.test/api/devices'
            && $request['uniqueId'] === 'IMEI-11');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://traccar.test/api/devices/11'
            && $request['name'] === 'Truck 11 updated');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://traccar.test/api/devices/11');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://traccar.test/api/permissions'
            && $request['deviceId'] === 11
            && $request['geofenceId'] === 4);
    }

    public function test_positions_trips_and_events_include_iso_ranges_and_fallbacks(): void
    {
        $from = Carbon::parse('2026-05-01 08:00:00', 'UTC');
        $to = Carbon::parse('2026-05-01 18:00:00', 'UTC');

        Http::fake([
            'https://traccar.test/api/positions*' => Http::sequence()
                ->push([['id' => 100, 'deviceId' => 77, 'latitude' => 36.7]], 200)
                ->push([['id' => 101, 'deviceId' => 77, 'latitude' => 36.8]], 200)
                ->push([], 200)
                ->push(null, 500),
            'https://traccar.test/api/reports/trips*' => Http::response([['distance' => 1200]], 200),
            'https://traccar.test/api/reports/events*' => Http::response([['type' => 'alarm']]),
        ]);

        $service = new TraccarService;

        self::assertSame([['id' => 100, 'deviceId' => 77, 'latitude' => 36.7]], $service->getPositions(77, $from, $to));
        self::assertSame(['id' => 101, 'deviceId' => 77, 'latitude' => 36.8], $service->getLastPosition(77));
        self::assertNull($service->getLastPosition(77));
        self::assertSame([], $service->getPositions(77));
        self::assertSame([['distance' => 1200]], $service->getTrips(77, $from, $to));
        self::assertSame([['type' => 'alarm']], $service->getEvents(77, $from, $to));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://traccar.test/api/positions?deviceId=77&from=2026-05-01T08%3A00%3A00%2B00%3A00&to=2026-05-01T18%3A00%3A00%2B00%3A00');
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://traccar.test/api/reports/trips?deviceId=77'));
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://traccar.test/api/reports/events?deviceId=77'));
    }
}
