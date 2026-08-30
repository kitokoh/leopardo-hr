<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-316..317 — Représentation API d'un billet.
 *
 * Interne au module (PA2-ARCH-010). Le `validation_code` (hash) n'est
 * jamais exposé — seul le numéro de billet et le statut.
 *
 * @mixin TravelTicket
 */
class TravelTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'booking_id' => $this->booking_id,
            'passenger_id' => $this->passenger_id,
            'status' => $this->status->value,
            'issued_at' => $this->issued_at,
            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,
            'checked_in_at' => $this->checked_in_at,
            'created_at' => $this->created_at,
        ];
    }
}
