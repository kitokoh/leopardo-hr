<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCountItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-504 (#6203) — Représentation API d'une ligne d'inventaire.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantInventoryCountItem
 */
class RestaurantInventoryCountItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'count_id' => $this->count_id,
            'ingredient_id' => $this->ingredient_id,
            'expected_qty' => (float) $this->expected_qty,
            'counted_qty' => $this->counted_qty !== null ? (float) $this->counted_qty : null,
            'variance_qty' => $this->variance_qty !== null ? (float) $this->variance_qty : null,
            'reason_code' => $this->reason_code,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
