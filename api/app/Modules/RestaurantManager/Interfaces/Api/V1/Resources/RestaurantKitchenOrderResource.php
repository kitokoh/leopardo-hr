<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-410 (#6197) — Représentation API d'une commande pour l'écran cuisine
 * (file par branche). Lignes actives uniquement, avec le libellé produit.
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantOrder
 */
class RestaurantKitchenOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'branch_id' => $this->branch_id,
            'table_id' => $this->table_id,
            'order_type' => $this->order_type->value,
            'status' => $this->status->value,
            'covers' => $this->covers,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'items' => $this->items
                ->filter(fn ($item) => $item->status === \App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus::ACTIVE)
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->product?->name,
                    'quantity' => (float) $item->quantity,
                    'line_index' => $item->line_index,
                    'status' => $item->status->value,
                ])->values(),
        ];
    }
}
