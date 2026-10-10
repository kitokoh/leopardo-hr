<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Resources;

use App\Modules\Vtc\Domain\Models\VtcDriver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Chauffeur VTC (BC-34 VTC, VTC-06/#8362) — allowlisté.
 *
 * Exposé à la console dispatch (positions temps réel, polling v1) et au
 * CRUD admin. Les positions historisées (données personnelles, RGPD)
 * restent internes — seule la DERNIÈRE position connue est exposée.
 *
 * @mixin VtcDriver
 */
final class VtcDriverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'status' => $this->status?->value,
            'vehicle_id' => $this->vehicle_id,
            'current_latitude' => $this->current_latitude,
            'current_longitude' => $this->current_longitude,
            'location_updated_at' => $this->location_updated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
