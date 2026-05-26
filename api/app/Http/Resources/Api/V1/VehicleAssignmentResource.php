<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\VehicleAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleAssignment
 */
class VehicleAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'employee_id' => $this->employee_id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'reason' => $this->reason,
            'employee' => $this->whenLoaded('employee'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
