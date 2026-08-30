<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-309 (#6039) — Représentation API d'un tarif de trajet par classe.
 *
 * Interne au module (PA2-ARCH-010). Montants en unités mineures (jamais de
 * flottant) ; `currency` = devise du tenant.
 *
 * @mixin TravelTripPrice
 */
class TravelTripPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'class_id' => $this->class_id,
            'adult_price_minor' => $this->adult_price_minor,
            'child_price_minor' => $this->child_price_minor,
            'currency' => $this->currency,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
