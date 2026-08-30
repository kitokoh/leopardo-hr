<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-605 (#6210) — Représentation API d'une livraison restaurant.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantDelivery
 */
class RestaurantDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'zone_id' => $this->zone_id,
            'rider_id' => $this->rider_id,
            'status' => $this->status->value,
            'fee_minor' => $this->fee_minor,
            'delivered_at' => $this->delivered_at,
            'delivered_to_contact' => $this->delivered_to_contact,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
