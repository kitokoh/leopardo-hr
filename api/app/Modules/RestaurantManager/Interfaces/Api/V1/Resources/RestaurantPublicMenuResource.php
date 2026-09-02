<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-805 (#6226) — Menu public d'un tenant (boutique en ligne).
 *
 * Exposé SANS auth utilisateur (jeton `X-Restaurant-Shop-Token`) : seuls les
 * produits actifs et disponibles du tenant courant sont renvoyés (scope
 * BelongsToCompany posé par le middleware `restaurant.public.shop`). Prix en
 * minor units, devise du tenant. Aucune donnée d'un autre tenant.
 *
 * @mixin RestaurantCategory
 */
class RestaurantPublicMenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'products' => $this->whenLoaded('products', fn () => $this->products
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'description' => $product->description_redacted,
                    'price_minor' => $product->price_minor,
                    'currency' => $product->currency,
                    'image_asset_id' => $product->image_asset_id,
                ])
                ->values()),
        ];
    }
}
