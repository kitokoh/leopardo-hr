<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-501 (#6200) — Ressource API d'un niveau de stock.
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
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'ingredient_id' => $this->ingredient_id,
            'quantity' => $this->quantity,
            'avg_cost_minor' => $this->avg_cost_minor,
            'reorder_level' => $this->reorder_level,
            'alert_threshold' => $this->alert_threshold,
            'is_below_threshold' => $this->alert_threshold !== null
                && (float) $this->quantity <= (float) $this->alert_threshold,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
