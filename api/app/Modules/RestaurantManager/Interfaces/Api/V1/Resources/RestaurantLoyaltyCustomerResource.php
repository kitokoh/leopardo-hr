<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;

/**
 * RESTO-606 (#6211) — Ressource API d'un client fidélité.
 */
/**
 * @mixin RestaurantLoyaltyCustomer
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
            'opted_in_at' => $this->resource->getAttribute('opted_in_at') !== null ? \Illuminate\Support\Carbon::parse($this->resource->getAttribute('opted_in_at'))->toIso8601String() : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }


}