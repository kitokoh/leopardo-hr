<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-312..316 — Représentation API d'une réservation.
 *
 * Interne au module (PA2-ARCH-010). Passagers exposés quand chargés ;
 * billets quand émis.
 *
 * @mixin TravelBooking
 */
class TravelBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'trip_id' => $this->trip_id,
            'status' => $this->status->value,
            'passenger_count' => $this->passenger_count,
            'total_amount_minor' => $this->total_amount_minor,
            'currency' => $this->currency,
            'booking_source' => $this->booking_source->value,
            'payment_status' => $this->payment_status->value,
            'expires_at' => $this->expires_at,
            'version' => $this->version,
            'passengers' => TravelPassengerResource::collection($this->whenLoaded('passengers')),
            'tickets' => TravelTicketResource::collection($this->whenLoaded('tickets')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
