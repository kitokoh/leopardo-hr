<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-504 (#6203) — Représentation API d'un inventaire physique.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantInventoryCount
 */
class RestaurantInventoryCountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'counted_at' => $this->counted_at,
            'status' => $this->status->value,
            'counted_by_user_id' => $this->counted_by_user_id,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'items' => RestaurantInventoryCountItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
