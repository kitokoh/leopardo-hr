<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plate_number' => $this->plate_number,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'type' => $this->type,
            'vin' => $this->vin,
            'fuel_type' => $this->fuel_type,
            'status' => $this->status,
            'mileage' => $this->mileage,
            'insurance_expiry' => $this->insurance_expiry?->toDateString(),
            'technical_control_expiry' => $this->technical_control_expiry?->toDateString(),
            'assigned_driver_id' => $this->assigned_driver_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
