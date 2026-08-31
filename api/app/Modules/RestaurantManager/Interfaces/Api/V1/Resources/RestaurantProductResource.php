<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-302 (#6183) — Représentation API d'un produit du catalogue.
 *
 * Les montants sont exposés en unités mineures entières (minor units).
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantProduct
 */
class RestaurantProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'category_id' => $this->category_id,
            'code' => $this->code,
            'name' => $this->name,
            'description_redacted' => $this->description_redacted,
            'price_minor' => $this->price_minor,
            'currency' => $this->currency,
            'cost_minor' => $this->cost_minor,
            'tax_rate_id' => $this->tax_rate_id,
            'is_available' => $this->is_available,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
