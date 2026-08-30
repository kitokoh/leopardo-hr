<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelRentalBooking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-320 (#6050) — Représentation API d'une réservation de location.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin TravelRentalBooking
 */
class TravelRentalBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'vehicle_id' => $this->vehicle_id,
            'customer_contact_id' => $this->customer_contact_id,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'total_amount_minor' => $this->total_amount_minor,
            'currency' => $this->currency,
            'deposit_amount_minor' => $this->deposit_amount_minor,
            'payment_status' => $this->payment_status->value,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
