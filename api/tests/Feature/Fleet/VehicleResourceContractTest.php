<?php

declare(strict_types=1);

namespace Tests\Feature\Fleet;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Resources\Api\V1\VehicleAlertResource;
use App\Http\Resources\Api\V1\VehicleTripResource;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Fleet\Domain\Models\VehicleAlert;
use App\Modules\Fleet\Domain\Models\VehicleTrip;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7399 — CONTRAT des ressources flotte.
 *
 * Non-régression : `VehicleTripResource` exposait `start_location`,
 * `end_location` et `purpose` (colonnes INEXISTANTES dans `vehicle_trips` :
 * toujours `null`) et omettait `start_address`/`end_address`/coordonnées/
 * vitesses/durée ; `VehicleAlertResource` exposait `severity`/`resolved_at`
 * (inexistants) et omettait la position GPS et l'acquittement.
 *
 * Le test verrouille une LISTE BLANCHE explicite des clés exposées et vérifie
 * que chacune (hors clé primaire et relation optionnelle) est une vraie
 * colonne `$fillable` du modèle — un champ fantôme réintroduit fait échouer
 * le test, à la fois côté ressource et côté HTTP.
 */
class VehicleResourceContractTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $company;

    protected Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
    }

    /** @test */
    public function trip_resource_exposes_every_real_route_column_and_no_ghost(): void
    {
        $vehicle = $this->vehicle();

        $trip = VehicleTrip::query()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $this->company->id,
            'driver_id' => $this->manager->id,
            'start_time' => '2026-09-12 06:00:00',
            'end_time' => '2026-09-12 08:15:00',
            'start_address' => 'Douala, Wouri, Littoral, Cameroun',
            'start_lat' => 4.0510564,
            'start_lng' => 9.7678687,
            'end_address' => 'Edéa, Sanaga-Maritime, Littoral, Cameroun',
            'end_lat' => 3.8,
            'end_lng' => 10.1333,
            'distance_km' => 250,
            'duration_minutes' => 135,
            'max_speed_kmh' => 76.86,
            'avg_speed_kmh' => 55.93,
        ]);

        // 1. Contrat de sérialisation : liste blanche exacte.
        $payload = (new VehicleTripResource($trip))->toArray(Request::create('/'));

        self::assertSame(VehicleTripResource::exposedKeys(), array_keys($payload));

        // 2. Aucune clé exposée n'est fantôme : chacune (hors id + relation)
        //    correspond à une colonne $fillable réelle du modèle.
        $derivedKeys = ['id', 'driver'];
        $ghostKeys = array_values(array_diff(
            array_diff(VehicleTripResource::exposedKeys(), $derivedKeys),
            (new VehicleTrip)->getFillable(),
        ));
        self::assertSame([], $ghostKeys, 'Champ(s) exposé(s) sans colonne réelle : '.implode(', ', $ghostKeys));

        // 3. Les champs fantômes historiques ne reviennent pas.
        self::assertArrayNotHasKey('start_location', $payload);
        self::assertArrayNotHasKey('end_location', $payload);
        self::assertArrayNotHasKey('purpose', $payload);

        // 4. Les vraies données d'itinéraire sortent bien.
        self::assertSame('Douala, Wouri, Littoral, Cameroun', $payload['start_address']);
        self::assertSame('Edéa, Sanaga-Maritime, Littoral, Cameroun', $payload['end_address']);
        self::assertSame(135, $payload['duration_minutes']);
        self::assertSame('250.00', $payload['distance_km']);
        self::assertSame('76.86', $payload['max_speed_kmh']);
        self::assertSame('55.93', $payload['avg_speed_kmh']);
        self::assertEqualsWithDelta(4.0510564, (float) $payload['start_lat'], 0.0000001);
    }

    /** @test */
    public function trip_endpoint_returns_the_real_route_shape(): void
    {
        $vehicle = $this->vehicle();

        VehicleTrip::query()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $this->company->id,
            'start_time' => '2026-09-12 06:00:00',
            'end_time' => '2026-09-12 08:15:00',
            'start_address' => 'Douala, Wouri, Littoral, Cameroun',
            'start_lat' => 4.0510564,
            'start_lng' => 9.7678687,
            'end_address' => 'Yaoundé, Centre, Cameroun',
            'end_lat' => 3.848,
            'end_lng' => 11.5021,
            'distance_km' => 250,
            'duration_minutes' => 135,
            'max_speed_kmh' => 76.86,
            'avg_speed_kmh' => 55.93,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/vehicles/'.$vehicle->id.'/trips')->assertOk();

        $expectedKeys = array_values(array_diff(VehicleTripResource::exposedKeys(), ['driver']));

        self::assertSame($expectedKeys, array_keys($response->json('data.0')));
        $response
            ->assertJsonPath('data.0.start_address', 'Douala, Wouri, Littoral, Cameroun')
            ->assertJsonPath('data.0.end_address', 'Yaoundé, Centre, Cameroun')
            ->assertJsonPath('data.0.duration_minutes', 135)
            ->assertJsonPath('data.0.max_speed_kmh', '76.86')
            ->assertJsonPath('data.0.avg_speed_kmh', '55.93')
            ->assertJsonMissingPath('data.0.start_location')
            ->assertJsonMissingPath('data.0.end_location')
            ->assertJsonMissingPath('data.0.purpose');
    }

    /** @test */
    public function alert_resource_exposes_gps_and_acknowledgement_and_no_ghost(): void
    {
        $vehicle = $this->vehicle();

        $alert = VehicleAlert::query()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $this->company->id,
            'type' => 'speeding',
            'message' => 'Vitesse excessive : 96 km/h (limite 80 km/h)',
            'latitude' => 4.0510564,
            'longitude' => 9.7678687,
            'speed' => 96.5,
            'acknowledged' => true,
            'acknowledged_by' => $this->manager->id,
        ]);

        $payload = (new VehicleAlertResource($alert))->toArray(Request::create('/'));

        self::assertSame(VehicleAlertResource::exposedKeys(), array_keys($payload));

        $ghostKeys = array_values(array_diff(
            array_diff(VehicleAlertResource::exposedKeys(), ['id']),
            (new VehicleAlert)->getFillable(),
        ));
        self::assertSame([], $ghostKeys, 'Champ(s) exposé(s) sans colonne réelle : '.implode(', ', $ghostKeys));

        self::assertArrayNotHasKey('severity', $payload);
        self::assertArrayNotHasKey('resolved_at', $payload);

        self::assertEqualsWithDelta(4.0510564, (float) $payload['latitude'], 0.0000001);
        self::assertEqualsWithDelta(9.7678687, (float) $payload['longitude'], 0.0000001);
        self::assertSame('96.50', $payload['speed']);
        self::assertTrue($payload['acknowledged']);
        self::assertSame($this->manager->id, $payload['acknowledged_by']);
    }

    /** @test */
    public function alert_endpoint_returns_gps_and_acknowledgement(): void
    {
        $vehicle = $this->vehicle();

        VehicleAlert::query()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $this->company->id,
            'type' => 'geofence_exit',
            'message' => 'Sortie de zone',
            'latitude' => 4.0510564,
            'longitude' => 9.7678687,
            'speed' => 42.25,
            'acknowledged' => false,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/vehicles/'.$vehicle->id.'/alerts')->assertOk();

        self::assertSame(VehicleAlertResource::exposedKeys(), array_keys($response->json('data.0')));
        $response
            ->assertJsonPath('data.0.type', 'geofence_exit')
            ->assertJsonPath('data.0.acknowledged', false)
            ->assertJsonPath('data.0.speed', '42.25')
            ->assertJsonMissingPath('data.0.severity')
            ->assertJsonMissingPath('data.0.resolved_at');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function vehicle(array $overrides = []): Vehicle
    {
        return Vehicle::query()->create(array_merge([
            'company_id' => $this->company->id,
            'plate_number' => 'DZ-7399-'.fake()->unique()->bothify('####'),
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'type' => 'van',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ], $overrides));
    }
}
