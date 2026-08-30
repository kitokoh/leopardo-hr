<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-402 (#6189) — Représentation API d'une commande restaurant.
 *
 * Totaux en minor units ; `items` et `payments` sont inclus quand chargés
 * (relations Eloquent pré-chargées). Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantOrder
 */
class RestaurantOrderResource extends JsonResource
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
            'pos_session_id' => $this->pos_session_id,
            'order_type' => $this->order_type->value,
            'table_id' => $this->table_id,
            'zone_id' => $this->zone_id,
            'covers' => $this->covers,
            'customer_contact_id' => $this->customer_contact_id,
            'rider_id' => $this->rider_id,
            'status' => $this->status->value,
            'subtotal_minor' => $this->subtotal_minor,
            'tax_minor' => $this->tax_minor,
            'discount_minor' => $this->discount_minor,
            'total_minor' => $this->total_minor,
            'currency' => $this->currency,
            'source' => $this->source->value,
            'note_redacted' => $this->note_redacted,
            'version' => $this->version,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'items' => RestaurantOrderItemResource::collection($this->whenLoaded('items')),
            'payments' => RestaurantOrderPaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
