<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-501 (#6200) — Ressource API d'un mouvement de stock.
 */
class RestaurantInventoryMovementResource extends JsonResource
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
            'ingredient_id' => $this->ingredient_id,
            'stock_level_id' => $this->stock_level_id,
            'quantity_delta' => $this->quantity_delta,
            'reason_code' => $this->reason_code,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'note_redacted' => $this->note_redacted,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
