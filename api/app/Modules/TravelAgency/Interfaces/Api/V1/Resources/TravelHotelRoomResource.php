<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelHotelRoom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-321 (#6051) — Représentation API d'une chambre d'hôtel.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin TravelHotelRoom
 */
class TravelHotelRoomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hotel_id' => $this->hotel_id,
            'type_code' => $this->type_code,
            'room_number' => $this->room_number,
            'capacity' => $this->capacity,
            'price_per_night_minor' => $this->price_per_night_minor,
            'currency' => $this->currency,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
