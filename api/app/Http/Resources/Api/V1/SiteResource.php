<?php

namespace App\Http\Resources\Api\V1;

use App\Core\Tenant\Domain\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Établissement exposé par l'API (BOS-T-01 / #8624).
 *
 * `timezone` et `currency` retombent sur celles du tenant quand elles ne sont
 * pas définies SUR l'établissement : le client n'a pas à connaître la règle de
 * repli, il reçoit une valeur exploitable.
 *
 * @mixin Site
 */
class SiteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'code' => $this->code,
            'site_type' => $this->site_type,
            'status' => $this->status,
            'address' => $this->address,
            'gps_lat' => $this->gps_lat,
            'gps_lng' => $this->gps_lng,
            'gps_radius_m' => $this->gps_radius_m,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
