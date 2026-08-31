<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-607 (#6212) — Ressource API d'une promotion.
 * RESTO-607 (#6212) — Représentation API d'une promotion restaurant.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantPromotion
 */
class RestaurantPromotionResource extends JsonResource
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
            'code' => $this->code,
            'title' => $this->title,
            'discount_type' => $this->discount_type,
            'value_minor' => $this->value_minor,
            'min_order_minor' => $this->min_order_minor,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'branch_id' => $this->branch_id,
            'code' => $this->code,
            'title' => $this->title,
            'discount_type' => $this->discount_type->value,
            'value_minor' => $this->value_minor,
            'min_order_minor' => $this->min_order_minor,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
