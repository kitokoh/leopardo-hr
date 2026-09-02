<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-502 (#6201) — Représentation API d'un bon de commande fournisseur.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantPurchaseOrder
 */
class RestaurantPurchaseOrderResource extends JsonResource
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
            'supplier_id' => $this->supplier_id,
            'status' => $this->status->value,
            'expected_at' => $this->expected_at,
            'received_at' => $this->received_at,
            'total_minor' => $this->total_minor,
            'currency' => $this->currency,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'items' => RestaurantPurchaseOrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
