<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-403 (#6190) — Représentation API d'une ligne d'article de commande.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantOrderItem
 */
class RestaurantOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'menu_id' => $this->menu_id,
            'quantity' => (float) $this->quantity,
            'unit_price_minor' => $this->unit_price_minor,
            'line_total_minor' => $this->line_total_minor,
            'tax_rate_id' => $this->tax_rate_id,
            'tax_minor' => $this->tax_minor,
            'status' => $this->status->value,
            'line_index' => $this->line_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
