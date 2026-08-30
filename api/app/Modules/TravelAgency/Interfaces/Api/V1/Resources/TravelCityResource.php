<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelCity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-301 (#6031) — Représentation API d'une ville du référentiel.
 *
 * Interne au module (PA2-ARCH-010) : ressource non partagée avec d'autres
 * modules, reste sous `Interfaces/Api/V1/Resources/`.
 *
 * @mixin TravelCity
 */
class TravelCityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_iso2' => $this->country_iso2,
            'name' => $this->name,
            'region' => $this->region,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status->value,
        ];
    }
}
