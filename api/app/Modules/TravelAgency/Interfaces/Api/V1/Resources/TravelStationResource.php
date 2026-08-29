<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelStation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-302 (#6032) — Représentation API d'une gare/terminal.
 *
 * Interne au module (PA2-ARCH-010).
 *
 * @mixin TravelStation
 */
class TravelStationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'city_id' => $this->city_id,
            'address' => $this->address,
            'contact_phone' => $this->contact_phone,
            'timezone' => $this->timezone,
            'is_terminal' => $this->is_terminal,
            'status' => $this->status?->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
