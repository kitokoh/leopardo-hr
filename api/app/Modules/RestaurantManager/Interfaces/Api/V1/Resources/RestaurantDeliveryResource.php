<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-605 (#6210) — Ressource API d'une livraison.
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
            'company_id' => $this->company_id,
            'order_id' => $this->order_id,
            'zone_id' => $this->zone_id,
            'rider_id' => $this->rider_id,
            'status' => $this->status,
            'fee_minor' => $this->fee_minor,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'delivered_to_contact' => $this->delivered_to_contact,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
