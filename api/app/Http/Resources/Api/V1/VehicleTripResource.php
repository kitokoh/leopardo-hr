<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\VehicleTrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
            'start_location' => $this->start_location,
            'end_location' => $this->end_location,
            'distance_km' => $this->distance_km,
            'purpose' => $this->purpose,
            'driver' => $this->whenLoaded('driver'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
