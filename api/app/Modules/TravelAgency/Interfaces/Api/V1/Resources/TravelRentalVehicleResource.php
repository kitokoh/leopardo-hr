<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-319 (#6049) — Représentation API d'un véhicule de location.
 *
 * Interne au module (PA2-ARCH-010). Images exposées quand chargées ;
 * dates de disponibilité au format `Y-m-d`.
 *
 * @mixin TravelRentalVehicle
 */
class TravelRentalVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'city_id' => $this->city_id,
            'price_per_day_minor' => $this->price_per_day_minor,
            'currency' => $this->currency,
            'available_from' => $this->available_from?->toDateString(),
            'available_until' => $this->available_until?->toDateString(),
            'owner_carrier_id' => $this->owner_carrier_id,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'images' => TravelRentalVehicleImageResource::collection($this->whenLoaded('images')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
