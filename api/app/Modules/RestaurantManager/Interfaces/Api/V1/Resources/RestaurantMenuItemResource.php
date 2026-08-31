<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantMenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-304 (#6185) — Représentation API d'une ligne de menu restaurant.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantMenuItem
 */
class RestaurantMenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_id' => $this->menu_id,
            'product_id' => $this->product_id,
            'position' => $this->position,
            'is_optional' => $this->is_optional,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
