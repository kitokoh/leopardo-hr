<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Fleet\Domain\Models\VehicleTrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * #7399 — CONTRAT STRICT : uniquement des colonnes réelles de `vehicle_trips`
 * (+ la relation optionnelle `driver`). Les champs fantômes historiques
 * (`start_location`, `end_location`, `purpose`) n'existent pas dans le schéma
 * et sortaient toujours à `null` — l'itinéraire (adresses + coordonnées +
 * vitesses + durée) n'était jamais exposé.
 *
 * Toute évolution doit être couverte par VehicleResourceContractTest.
 *
 * @mixin VehicleTrip
 */
class VehicleTripResource extends JsonResource
{
    /** @return array<int, string> */
    public static function exposedKeys(): array
    {
        return [
            'id',
            'vehicle_id',
            'driver_id',
            'start_time',
            'end_time',
            'start_address',
            'start_lat',
            'start_lng',
            'end_address',
            'end_lat',
            'end_lng',
            'distance_km',
            'duration_minutes',
            'max_speed_kmh',
            'avg_speed_kmh',
            'driver',
            'created_at',
        ];
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'start_address' => $this->start_address,
            'start_lat' => $this->start_lat,
            'start_lng' => $this->start_lng,
            'end_address' => $this->end_address,
            'end_lat' => $this->end_lat,
            'end_lng' => $this->end_lng,
            'distance_km' => $this->distance_km,
            'duration_minutes' => $this->duration_minutes,
            'max_speed_kmh' => $this->max_speed_kmh,
            'avg_speed_kmh' => $this->avg_speed_kmh,
            'driver' => $this->whenLoaded('driver'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
