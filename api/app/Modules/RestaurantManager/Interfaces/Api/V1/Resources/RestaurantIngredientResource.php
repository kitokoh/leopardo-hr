<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-303 (#6184) — Représentation API d'un ingrédient.
 *
 * `unit_code` référence `restaurant_units.code` par valeur ;
 * `avg_cost_minor` est exposé en unités mineures entières.
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantIngredient
 */
class RestaurantIngredientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'code' => $this->code,
            'name' => $this->name,
            'unit_code' => $this->unit_code,
            'avg_cost_minor' => $this->avg_cost_minor,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
