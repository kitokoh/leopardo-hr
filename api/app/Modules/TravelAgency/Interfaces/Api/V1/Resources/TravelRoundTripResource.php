<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelRoundTrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-802 (#6093) — Représentation API d'un aller-retour.
 *
 * Interne au module (PA2-ARCH-010). Statut dérivé des deux réservations.
 *
 * @mixin TravelRoundTrip
 */
class TravelRoundTripResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status()->value,
            'outbound' => new TravelBookingResource($this->whenLoaded('bookingOutbound')),
            'return' => new TravelBookingResource($this->whenLoaded('bookingReturn')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
