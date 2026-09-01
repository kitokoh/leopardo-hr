<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-605 (#6210) — Représentation API d'un livreur restaurant.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantDeliveryRider
 */
class RestaurantDeliveryRiderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'employee_id' => $this->employee_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'vehicle_code' => $this->vehicle_code,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
