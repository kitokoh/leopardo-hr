<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicleImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-319 (#6049) — Représentation API d'une image de véhicule de location.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin TravelRentalVehicleImage
 */
class TravelRentalVehicleImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'asset_id' => $this->asset_id,
            'position' => $this->position,
        ];
    }
}
