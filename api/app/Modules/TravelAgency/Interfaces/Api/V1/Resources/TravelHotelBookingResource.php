<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelHotelBooking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * M4 — Représentation API d'une réservation hôtelière.
 *
 * @mixin TravelHotelBooking
 */
class TravelHotelBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'room_id' => $this->room_id,
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'rooms' => $this->rooms,
            'nights' => $this->nights,
            'total_amount_minor' => $this->total_amount_minor,
            'currency' => $this->currency,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
