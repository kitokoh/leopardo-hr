<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-301 (#6182) — Représentation API d'une table du plan de salle.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantTable
 */
class RestaurantTableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'zone_id' => $this->zone_id,
            'label' => $this->label,
            'capacity' => $this->capacity,
            'min_covers' => $this->min_covers,
            'is_mergeable' => $this->is_mergeable,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
