<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-304 (#6185) — Représentation API d'un horaire d'ouverture restaurant.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantHour
 */
class RestaurantHourResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'day_of_week' => $this->day_of_week,
            'opens_at' => $this->opens_at,
            'closes_at' => $this->closes_at,
            'is_closed' => $this->is_closed,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
