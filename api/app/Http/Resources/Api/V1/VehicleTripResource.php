<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Fleet\Domain\Models\VehicleTrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trajet agrégé d'un véhicule (itinéraire) — alimenté par la synchro Traccar
 * (`POST /api/v1/tracking/sync-trips`).
 *
 * #7399 — la ressource exposait `start_location`, `end_location` et `purpose`,
 * trois attributs **inexistants** sur `VehicleTrip` (absents de `$fillable` et
 * de la migration `2026_05_11_000002_create_tracking_tables.php`) : ils valaient
 * donc toujours `null`, et les vraies colonnes — origine, destination,
 * coordonnées, durée, vitesses — n'étaient jamais exposées. L'itinéraire d'un
 * véhicule de service était illisible côté client.
 *
 * @mixin VehicleTrip
 */
class VehicleTripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),

            // Origine / destination (colonnes réelles).
            'start_address' => $this->start_address,
            'start_lat' => $this->start_lat,
            'start_lng' => $this->start_lng,
            'end_address' => $this->end_address,
            'end_lat' => $this->end_lat,
            'end_lng' => $this->end_lng,

            // Métriques du trajet (déjà en base, jamais exposées avant #7399).
            'distance_km' => $this->distance_km,
            'duration_minutes' => $this->duration_minutes,
            'max_speed_kmh' => $this->max_speed_kmh,
            'avg_speed_kmh' => $this->avg_speed_kmh,

            'driver' => $this->whenLoaded('driver'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
