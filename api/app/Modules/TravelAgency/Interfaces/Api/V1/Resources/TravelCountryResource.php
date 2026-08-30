<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-301 (#6031) — Représentation API d'un pays du référentiel.
 *
 * Interne au module (PA2-ARCH-010) : ressource non partagée avec d'autres
 * modules, reste sous `Interfaces/Api/V1/Resources/`.
 *
 * @mixin TravelCountry
 */
class TravelCountryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iso2' => $this->iso2,
            'iso3' => $this->iso3,
            'name' => $this->name,
            'phone_code' => $this->phone_code,
            'status' => $this->status->value,
        ];
    }
}
