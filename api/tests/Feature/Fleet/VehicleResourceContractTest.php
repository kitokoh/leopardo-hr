<?php

declare(strict_types=1);

namespace Tests\Feature\Fleet;

use App\Modules\Fleet\Domain\Models\VehicleAlert;
use App\Modules\Fleet\Domain\Models\VehicleTrip;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * #7399 — Garde anti « champs fantômes » sur les ressources de la flotte.
 *
 * `VehicleTripResource` exposait `start_location`, `end_location` et `purpose`,
 * `VehicleAlertResource` exposait `severity` et `resolved_at` : cinq attributs
 * qui n'existaient ni dans `$fillable` ni dans la migration
 * `2026_05_11_000002_create_tracking_tables.php`. Ils valaient donc toujours
 * `null`, et les vraies colonnes (origine, destination, coordonnées, durée,
 * vitesses, GPS de l'alerte) n'étaient jamais exposées.
 *
 * Ce test vérifie que **chaque clé exposée par une ressource correspond à un
 * attribut réel du modèle** — pas seulement le code HTTP 200. C'est exactement
 * le type d'assertion qui manquait : `FleetControllerTest` passait au vert
 * alors que l'itinéraire était illisible.
 */
class VehicleResourceContractTest extends TestCase
{
    /**
     * Clés calculées (accesseurs / relations), légitimement absentes des
     * colonnes. Elles sont listées explicitement pour que tout ajout futur
     * doive être justifié ici.
     *
     * @var list<string>
     */
    private const COMPUTED_KEYS = ['driver', 'severity'];

    public function test_vehicle_trip_resource_only_exposes_real_attributes(): void
    {
        $this->assertResourceKeysExistOnModel(
            new \App\Http\Resources\Api\V1\VehicleTripResource(new VehicleTrip),
            new VehicleTrip,
        );
    }

    public function test_vehicle_alert_resource_only_exposes_real_attributes(): void
    {
        $this->assertResourceKeysExistOnModel(
            new \App\Http\Resources\Api\V1\VehicleAlertResource(new VehicleAlert),
            new VehicleAlert,
        );
    }

    public function test_vehicle_trip_exposes_the_itinerary_fields(): void
    {
        $keys = array_keys(
            (new \App\Http\Resources\Api\V1\VehicleTripResource(new VehicleTrip))->toArray(request())
        );

        // Le besoin client : « suivre l'itinéraire de mes véhicules de service ».
        foreach (['start_address', 'end_address', 'distance_km', 'duration_minutes'] as $key) {
            self::assertContains(
                $key,
                $keys,
                "VehicleTripResource doit exposer `{$key}` — sans quoi l'itinéraire d'un véhicule reste illisible (#7399).",
            );
        }

        foreach (['start_location', 'end_location', 'purpose'] as $ghost) {
            self::assertNotContains(
                $ghost,
                $keys,
                "`{$ghost}` n'existe pas sur VehicleTrip : ne jamais le réexposer (#7399).",
            );
        }
    }

    public function test_vehicle_alert_severity_is_derived_from_type(): void
    {
        $alert = new VehicleAlert(['type' => 'sos']);
        self::assertSame('critical', $alert->severity);

        $alert = new VehicleAlert(['type' => 'speeding']);
        self::assertSame('high', $alert->severity);

        $alert = new VehicleAlert(['type' => 'idle']);
        self::assertSame('low', $alert->severity);

        // Type inconnu : fail-open vers `low`, jamais vers `critical`.
        $alert = new VehicleAlert(['type' => 'type_inconnu_xyz']);
        self::assertSame('low', $alert->severity);
    }

    /**
     * Vérifie que chaque clé exposée est un attribut réel du modèle
     * (colonne déclarée en `$fillable`, cast, accesseur ou relation).
     */
    private function assertResourceKeysExistOnModel(\Illuminate\Http\Resources\Json\JsonResource $resource, Model $model): void
    {
        $payload = $resource->toArray(request());
        /** @var array<int, string> $fillable */
        $fillable = $model->getFillable();
        $casts = array_keys($model->getCasts());

        foreach (array_keys($payload) as $key) {
            if (in_array($key, self::COMPUTED_KEYS, true)) {
                continue;
            }

            $accessor = 'get'.str_replace(' ', '', ucwords(str_replace('_', ' ', $key))).'Attribute';

            $isReal = in_array($key, $fillable, true)
                || in_array($key, $casts, true)
                || method_exists($model, $key)
                || method_exists($model, $accessor);

            self::assertTrue(
                $isReal,
                sprintf(
                    'La clé `%s` exposée par %s n\'existe pas sur %s (colonne, $fillable, cast, accesseur ou relation) — champ fantôme (#7399).',
                    $key,
                    $resource::class,
                    $model::class,
                ),
            );
        }
    }
}
