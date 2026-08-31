<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-606 (#6211) — Ressource API d'un client fidélité.
 */
class RestaurantLoyaltyCustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'customer_contact_id' => $this->customer_contact_id,
            'points' => $this->points,
            'tier_code' => $this->tier_code,
            'opted_in_at' => $this->opted_in_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
