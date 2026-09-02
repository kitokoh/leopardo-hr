<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantReceiving;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-503 (#6202) — Représentation API d'une réception de marchandises.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantReceiving
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
            'reference' => $this->reference,
            'branch_id' => $this->branch_id,
            'purchase_order_id' => $this->purchase_order_id,
            'supplier_id' => $this->supplier_id,
            'received_at' => $this->received_at,
            'note_redacted' => $this->note_redacted,
            'created_at' => $this->created_at,
        ];
    }
}
