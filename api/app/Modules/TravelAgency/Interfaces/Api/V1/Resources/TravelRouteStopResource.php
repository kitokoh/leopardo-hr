<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelRouteStop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-307 (#6037) — Représentation API d'une étape de route.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin TravelRouteStop
 */
class TravelRouteStopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'route_id' => $this->route_id,
            'city_id' => $this->city_id,
            'rank' => $this->rank,
            'is_stopover' => $this->is_stopover,
            'min_duration_min' => $this->min_duration_min,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
