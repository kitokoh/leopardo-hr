<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-303 (#6184) — Représentation API d'un taux de TVA.
 *
 * `rate_bps` exprime le taux en points de base (ex. 1900 = 19 %).
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantTaxRate
 */
class RestaurantTaxRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'rate_bps' => $this->rate_bps,
            'is_default' => $this->is_default,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
