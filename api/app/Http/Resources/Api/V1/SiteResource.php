<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Site */
class SiteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'address' => $this->address,
            'gps_lat' => $this->gps_lat,
            'gps_lng' => $this->gps_lng,
            'gps_radius_m' => $this->gps_radius_m,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
