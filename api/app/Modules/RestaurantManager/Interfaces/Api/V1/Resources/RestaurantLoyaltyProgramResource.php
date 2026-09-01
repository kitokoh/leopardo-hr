<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyProgram;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-606 (#6211) — Représentation API d'un programme fidélité.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantLoyaltyProgram
 */
class RestaurantLoyaltyProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'points_per_amount_minor' => $this->points_per_amount_minor,
            'redeem_rate_minor' => $this->redeem_rate_minor,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
