<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-302 (#6183) — Représentation API d'un lien recette produit/ingrédient.
 *
 * `quantity` est exposé avec 3 décimales (cast Eloquent `decimal:3`) ;
 * `unit_code` référence `restaurant_units.code` par valeur.
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantProductIngredient
 */
class RestaurantProductIngredientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'ingredient_id' => $this->ingredient_id,
            'quantity' => $this->quantity,
            'unit_code' => $this->unit_code,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
