<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-502 (#6201) — Représentation API d'une ligne de bon de commande.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantPurchaseOrderItem
 */
class RestaurantPurchaseOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'ingredient_id' => $this->ingredient_id,
            'quantity' => (float) $this->quantity,
            'unit_price_minor' => $this->unit_price_minor,
            'line_total_minor' => $this->line_total_minor,
            'created_at' => $this->created_at,
        ];
    }
}
