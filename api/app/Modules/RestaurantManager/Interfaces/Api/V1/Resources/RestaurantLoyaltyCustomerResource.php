<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-606 (#6211) — Représentation API d'un compte fidélité client.
 *
 * Interne au module (PA2-ARCH-010). Le solde est exposé, jamais le contact
 * lui-même (les données clients restent dans le CRM).
 *
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
            'customer_contact_id' => $this->customer_contact_id,
            'points' => $this->points,
            'tier_code' => $this->tier_code,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
