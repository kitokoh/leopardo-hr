<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stop d'une tournée (DELIVERY-202, issue #6286) — allowlisté.
 *
 * @mixin \App\Modules\Delivery\Domain\Models\DeliveryStop
 */
final class DeliveryRouteStopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_id' => $this->delivery_id,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'address' => $this->address,
            'contact' => $this->contact,
            'eta' => $this->eta?->toIso8601String(),
            'etd' => $this->etd?->toIso8601String(),
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
        ];
    }
}
