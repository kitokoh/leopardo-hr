<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-501 (#6200) — Représentation API d'un mouvement de stock.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantInventoryMovement
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
            'branch_id' => $this->branch_id,
            'ingredient_id' => $this->ingredient_id,
            'ingredient_name' => $this->whenLoaded('ingredient', fn () => $this->ingredient?->name),
            'stock_level_id' => $this->stock_level_id,
            'quantity_delta' => (float) $this->quantity_delta,
            'reason_code' => $this->reason_code->value,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'note_redacted' => $this->note_redacted,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
        ];
    }
}
