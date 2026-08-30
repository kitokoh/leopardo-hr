<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelHotel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-321 (#6051) — Représentation API d'un hôtel du catalogue.
 *
 * Interne au module (PA2-ARCH-010). Chambres exposées quand chargées
 * (évite les N+1 dans les listes).
 *
 * @mixin TravelHotel
 */
class TravelHotelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city_id' => $this->city_id,
            'classification' => $this->classification,
            'address' => $this->address,
            'contact_phone' => $this->contact_phone,
            'description_redacted' => $this->description_redacted,
            'status' => $this->status->value,
            'rooms' => TravelHotelRoomResource::collection($this->whenLoaded('rooms')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
