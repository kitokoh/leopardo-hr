<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-502 (#6201) — Ressource API d'un bon de commande fournisseur.
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
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'supplier_id' => $this->supplier_id,
            'reference' => $this->reference,
            'status' => $this->status,
            'expected_at' => $this->expected_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'total_minor' => $this->total_minor,
            'currency' => $this->currency,
            'items' => RestaurantPurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
