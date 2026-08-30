<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-501 (#6200) — Représentation API d'un niveau de stock.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantStockLevel
 */
class RestaurantStockLevelResource extends JsonResource
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
            'quantity' => (float) $this->quantity,
            'avg_cost_minor' => $this->avg_cost_minor,
            'reorder_level' => $this->reorder_level !== null ? (float) $this->reorder_level : null,
            'alert_threshold' => $this->alert_threshold !== null ? (float) $this->alert_threshold : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
