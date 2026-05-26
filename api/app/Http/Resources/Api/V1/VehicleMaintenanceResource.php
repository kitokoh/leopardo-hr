<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\VehicleMaintenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleMaintenance
 */
class VehicleMaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'type' => $this->type,
            'description' => $this->description,
            'service_date' => $this->service_date?->toDateString(),
            'cost' => $this->cost,
            'currency' => $this->currency,
            'provider' => $this->provider,
            'next_service_date' => $this->next_service_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
