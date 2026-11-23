<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelHotelRoomInventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * M4 — Représentation API d'une ligne d'inventaire (chambre × date).
 *
 * @mixin TravelHotelRoomInventory
 */
class TravelHotelRoomInventoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'stay_date' => $this->stay_date->toDateString(),
            'rooms_total' => $this->rooms_total,
            'rooms_booked' => $this->rooms_booked,
            'rooms_remaining' => $this->remaining(),
            'status' => $this->status,
        ];
    }
}
