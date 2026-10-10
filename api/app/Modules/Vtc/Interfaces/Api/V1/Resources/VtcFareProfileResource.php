<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Resources;

use App\Modules\Vtc\Domain\Models\VtcFareProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Grille tarifaire VTC (BC-34 VTC, VTC-06/#8362) — allowlistée.
 *
 * @mixin VtcFareProfile
 */
final class VtcFareProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currency' => $this->currency,
            'base_minor' => $this->base_minor,
            'per_km_minor' => $this->per_km_minor,
            'per_minute_minor' => $this->per_minute_minor,
            'minimum_minor' => $this->minimum_minor,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
