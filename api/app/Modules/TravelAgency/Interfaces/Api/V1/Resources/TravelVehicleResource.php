<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-306 (#6036) — Représentation API d'un véhicule de la flotte.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin TravelVehicle
 */
class TravelVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'registration_number' => $this->registration_number,
            'seat_capacity' => $this->seat_capacity,
            'carrier_id' => $this->carrier_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
