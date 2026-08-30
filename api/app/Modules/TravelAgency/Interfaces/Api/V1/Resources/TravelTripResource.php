<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-308 (#6038) — Représentation API d'un trajet daté.
 *
 * Interne au module (PA2-ARCH-010). Tarifs et étapes exposés quand chargés
 * (évite les N+1 dans les listes).
 *
 * @mixin TravelTrip
 */
class TravelTripResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'route_id' => $this->route_id,
            'carrier_id' => $this->carrier_id,
            'vehicle_id' => $this->vehicle_id,
            'departure_date' => $this->departure_date->toDateString(),
            'departure_time' => $this->departure_time,
            'arrival_date' => $this->arrival_date->toDateString(),
            'arrival_time' => $this->arrival_time,
            'means_of_transport' => $this->means_of_transport,
            'total_seats' => $this->total_seats,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'prices' => TravelTripPriceResource::collection($this->whenLoaded('prices')),
            'route' => new TravelRouteResource($this->whenLoaded('route')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
