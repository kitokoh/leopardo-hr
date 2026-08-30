<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-307 (#6037) — Représentation API d'une route avec ses étapes.
 *
 * Interne au module (PA2-ARCH-010). Les étapes sont chargées triées par
 * `rank` (scope du modèle) et exposées telles quelles.
 *
 * @mixin TravelRoute
 */
class TravelRouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'origin_city_id' => $this->origin_city_id,
            'destination_city_id' => $this->destination_city_id,
            'distance_km' => $this->distance_km,
            'duration_min' => $this->duration_min,
            'status' => $this->status,
            'stops' => TravelRouteStopResource::collection($this->whenLoaded('stops')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
