<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyPointsMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-606 (#6211) — Représentation API d'un mouvement de points fidélité.
 *
 * Journal des gains/échanges (delta signé, motif contrôlé, commande source).
 *
 * @mixin RestaurantLoyaltyPointsMovement
 */
class RestaurantLoyaltyPointsMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'delta' => $this->delta,
            'reason_code' => $this->reason_code instanceof \BackedEnum ? $this->reason_code->value : $this->reason_code,
            'order_id' => $this->order_id,
            'reference_id' => $this->reference_id,
            'created_at' => $this->created_at,
        ];
    }
}
