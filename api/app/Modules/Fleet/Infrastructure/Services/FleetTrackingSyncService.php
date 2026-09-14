<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Infrastructure\Services;

use App\Modules\Attendance\Infrastructure\Services\TraccarService;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Fleet\Domain\Models\VehiclePosition;
use App\Modules\Fleet\Domain\Models\VehicleTrip;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * #7401 — synchronisation Traccar de la flotte : devices → positions → trajets.
 *
 * Une seule implémentation pour les trois étapes, partagée par la commande
 * `leopardo:fleet:sync` (planifiée) et les endpoints manuels
 * `POST /tracking/sync-*` — avant ce service, la logique vivait uniquement dans
 * le contrôleur et l'endpoint `sync-positions` n'écrivait rien.
 *
 * Toutes les opérations sont **bornées** (limite de véhicules par tenant,
 * plafond de lignes par appareil) et **idempotentes** :
 * - positions : `insertOrIgnore` adossé à l'index unique
 *   `(company_id, traccar_position_id)` ;
 * - trajets : `insertOrIgnore` adossé à l'index unique partiel
 *   `(company_id, traccar_trip_id)` (#3369).
 *
 * Le service travaille **pour une entreprise donnée** : l'itération des tenants
 * et le basculement de `search_path` sont de la responsabilité de l'appelant
 * (`TenantManager::withinTenant()`), ce qui garde ce service utilisable depuis
 * une requête HTTP (contexte déjà posé par `TenantMiddleware`) comme depuis une
 * commande.
 */
final class FleetTrackingSyncService
{
    /** Traccar exprime les vitesses en nœuds ; le contrat de stockage est en km/h. */
    public const KNOTS_TO_KMH = 1.852;

    /** Profondeur maximale de la fenêtre synchronisée (jours). */
    public const MAX_WINDOW_DAYS = 90;

    /** Plafond de positions écrites par appareil et par passe. */
    public const MAX_POSITIONS_PER_DEVICE = 500;

    /** Plafond de trajets écrits par appareil et par passe. */
    public const MAX_TRIPS_PER_DEVICE = 500;

    public function __construct(private readonly TraccarService $traccar) {}

    /**
     * Liste des appareils Traccar (un seul appel HTTP, partagé par tous les
     * tenants synchronisés dans la même passe).
     *
     * @return array<int|string, mixed>
     */
    public function devices(): array
    {
        return $this->traccar->getDevices();
    }

    /**
     * Appaire les véhicules de l'entreprise avec les appareils Traccar par
     * `traccar_unique_id` (le seul lien stable : l'id d'appareil change si le
     * compte Traccar est recréé).
     *
     * @param  array<int|string, mixed>  $devices
     * @return array{devices:int, linked:int}
     */
    public function syncDevices(string $companyId, array $devices): array
    {
        $linked = 0;

        foreach ($devices as $device) {
            if (! is_array($device)) {
                continue;
            }

            $uniqueId = $device['uniqueId'] ?? null;
            $deviceId = $device['id'] ?? null;

            if (! is_string($uniqueId) || $uniqueId === '' || ! is_numeric($deviceId)) {
                continue;
            }

            $vehicle = Vehicle::query()
                ->where('company_id', $companyId)
                ->where('traccar_unique_id', $uniqueId)
                ->first();

            if (! $vehicle instanceof Vehicle) {
                continue;
            }

            $vehicle->update(['traccar_device_id' => (int) $deviceId]);
            $linked++;
        }

        return ['devices' => count($devices), 'linked' => $linked];
    }

    /**
     * Historique GPS : `GET /api/positions?deviceId=&from=&to=` pour chaque
     * véhicule suivi, écrit dans `vehicle_positions` sans doublon.
     *
     * @return array{written:int, seen:int, vehicles:int, vehicles_with_position:int}
     */
    public function syncPositions(string $companyId, Carbon $from, Carbon $to, ?int $vehicleLimit = null): array
    {
        $written = 0;
        $seen = 0;
        $vehiclesWithPosition = 0;

        $vehicles = $this->trackedVehicles($companyId, $vehicleLimit);

        foreach ($vehicles as $vehicle) {
            $positions = $this->traccar->getPositions((int) $vehicle->traccar_device_id, $from, $to);

            $rows = [];

            foreach ($positions as $position) {
                if (! is_array($position)) {
                    continue;
                }

                $row = $this->positionRow($vehicle, $position);

                if ($row === null) {
                    continue;
                }

                $rows[] = $row;

                if (count($rows) >= self::MAX_POSITIONS_PER_DEVICE) {
                    break;
                }
            }

            $seen += count($rows);

            if ($rows === []) {
                continue;
            }

            $vehiclesWithPosition++;
            $written += (int) VehiclePosition::query()->insertOrIgnore($rows);
        }

        return [
            'written' => $written,
            'seen' => $seen,
            'vehicles' => $vehicles->count(),
            'vehicles_with_position' => $vehiclesWithPosition,
        ];
    }

    /**
     * Instantané GPS (endpoint `sync-positions`) : dernière position connue de
     * chaque véhicule suivi, persistée dans `vehicle_positions`.
     *
     * @return array{written:int, vehicles:int, vehicles_with_position:int}
     */
    public function syncLatestPositions(string $companyId, ?int $vehicleLimit = null): array
    {
        $written = 0;
        $vehiclesWithPosition = 0;

        $vehicles = $this->trackedVehicles($companyId, $vehicleLimit);

        foreach ($vehicles as $vehicle) {
            $position = $this->traccar->getLastPosition((int) $vehicle->traccar_device_id);

            if ($position === null) {
                continue;
            }

            $row = $this->positionRow($vehicle, $position);

            if ($row === null) {
                continue;
            }

            $vehiclesWithPosition++;
            $written += (int) VehiclePosition::query()->insertOrIgnore([$row]);
        }

        return [
            'written' => $written,
            'vehicles' => $vehicles->count(),
            'vehicles_with_position' => $vehiclesWithPosition,
        ];
    }

    /**
     * Trajets Traccar (`GET /api/reports/trips`) convertis en itinéraires :
     * km, minutes et km/h (Traccar renvoie mètres, millisecondes et nœuds).
     *
     * @return array{written:int, vehicles:int}
     */
    public function syncTrips(string $companyId, Carbon $from, Carbon $to, ?int $vehicleLimit = null): array
    {
        $written = 0;

        $vehicles = $this->trackedVehicles($companyId, $vehicleLimit);

        foreach ($vehicles as $vehicle) {
            $trips = $this->traccar->getTrips((int) $vehicle->traccar_device_id, $from, $to);

            $rows = [];

            foreach ($trips as $trip) {
                if (! is_array($trip)) {
                    continue;
                }

                $row = $this->tripRow($vehicle, $trip);

                if ($row === null) {
                    continue;
                }

                $rows[] = $row;

                if (count($rows) >= self::MAX_TRIPS_PER_DEVICE) {
                    break;
                }
            }

            if ($rows === []) {
                continue;
            }

            $written += (int) VehicleTrip::query()->insertOrIgnore($rows);
        }

        return ['written' => $written, 'vehicles' => $vehicles->count()];
    }

    /**
     * @return Collection<int, Vehicle>
     */
    private function trackedVehicles(string $companyId, ?int $vehicleLimit): Collection
    {
        $query = Vehicle::query()
            ->where('company_id', $companyId)
            ->whereNotNull('traccar_device_id')
            ->orderBy('id');

        if ($vehicleLimit !== null) {
            $query->limit(max(1, $vehicleLimit));
        }

        return $query->get();
    }

    /**
     * @param  array<mixed, mixed>  $position
     * @return array<string, mixed>|null
     */
    private function positionRow(Vehicle $vehicle, array $position): ?array
    {
        $recordedAt = $position['fixTime'] ?? $position['deviceTime'] ?? null;

        if (! is_string($recordedAt) || $recordedAt === '') {
            return null;
        }

        $positionId = $position['id'] ?? null;
        $speed = $position['speed'] ?? null;

        return [
            'vehicle_id' => $vehicle->id,
            'company_id' => $vehicle->company_id,
            'device_id' => (int) $vehicle->traccar_device_id,
            'traccar_position_id' => is_numeric($positionId) ? (int) $positionId : null,
            'latitude' => is_numeric($position['latitude'] ?? null) ? $position['latitude'] : null,
            'longitude' => is_numeric($position['longitude'] ?? null) ? $position['longitude'] : null,
            'speed_kmh' => is_numeric($speed) ? round(((float) $speed) * self::KNOTS_TO_KMH, 2) : null,
            'recorded_at' => Carbon::parse($recordedAt),
            'created_at' => now(),
        ];
    }

    /**
     * @param  array<mixed, mixed>  $trip
     * @return array<string, mixed>|null
     */
    private function tripRow(Vehicle $vehicle, array $trip): ?array
    {
        $tripId = $trip['id'] ?? null;

        // Sans identifiant Traccar, aucun moyen de dédupliquer (l'index unique
        // partiel porte sur `traccar_trip_id` non nul) → on n'écrit pas.
        if (! is_numeric($tripId)) {
            return null;
        }

        $startTime = $trip['startTime'] ?? null;
        $endTime = $trip['endTime'] ?? null;

        return [
            'vehicle_id' => $vehicle->id,
            'company_id' => $vehicle->company_id,
            'driver_id' => $vehicle->assigned_driver_id,
            'start_time' => is_string($startTime) ? Carbon::parse($startTime) : now(),
            'end_time' => is_string($endTime) ? Carbon::parse($endTime) : null,
            'start_lat' => is_numeric($trip['startLat'] ?? null) ? $trip['startLat'] : null,
            'start_lng' => is_numeric($trip['startLon'] ?? null) ? $trip['startLon'] : null,
            'start_address' => is_string($trip['startAddress'] ?? null) ? $trip['startAddress'] : null,
            'end_lat' => is_numeric($trip['endLat'] ?? null) ? $trip['endLat'] : null,
            'end_lng' => is_numeric($trip['endLon'] ?? null) ? $trip['endLon'] : null,
            'end_address' => is_string($trip['endAddress'] ?? null) ? $trip['endAddress'] : null,
            'distance_km' => round(((float) ($trip['distance'] ?? 0)) / 1000, 2),
            'duration_minutes' => (int) round(((float) ($trip['duration'] ?? 0)) / 60000),
            'max_speed_kmh' => round(((float) ($trip['maxSpeed'] ?? 0)) * self::KNOTS_TO_KMH, 2),
            'avg_speed_kmh' => round(((float) ($trip['averageSpeed'] ?? 0)) * self::KNOTS_TO_KMH, 2),
            'traccar_trip_id' => (int) $tripId,
            'created_at' => now(),
        ];
    }
}
