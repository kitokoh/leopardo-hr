<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-503 (#6202) — Ressource API d'une réception de marchandises.
 */
class RestaurantReceivingResource extends JsonResource
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
            'purchase_order_id' => $this->purchase_order_id,
            'supplier_id' => $this->supplier_id,
            'reference' => $this->reference,
            'received_at' => $this->received_at?->toIso8601String(),
            'note_redacted' => $this->note_redacted,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
