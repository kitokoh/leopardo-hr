<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Resources;

use App\Modules\Vtc\Domain\Models\VtcVehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Véhicule VTC (BC-34 VTC, VTC-06/#8362) — allowlisté.
 *
 * @mixin VtcVehicle
 */
final class VtcVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plate' => $this->plate,
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'seats' => $this->seats,
            'category' => $this->category?->value,
            'status' => $this->status?->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
