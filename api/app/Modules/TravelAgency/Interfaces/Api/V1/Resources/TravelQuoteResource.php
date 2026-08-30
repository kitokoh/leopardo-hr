<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-803 (#6094) — Représentation API d'un devis de groupe.
 *
 * Interne au module (PA2-ARCH-010). Les passagers nominatifs ne sont exposés
 * que sur le détail (jamais dans les listes).
 *
 * @mixin TravelQuote
 */
class TravelQuoteResource extends JsonResource
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
            'customer_contact_id' => $this->customer_contact_id,
            'passenger_count' => $this->passenger_count,
            'total_amount_minor' => $this->total_amount_minor,
            'currency' => $this->currency,
            'expires_at' => $this->expires_at,
            'booking_id' => $this->booking_id,
            'passengers' => $this->when($this->withPassengers(), $this->passengers_json ?? []),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function withPassengers(): bool
    {
        return (bool) request()->query('with_passengers');
    }
}
