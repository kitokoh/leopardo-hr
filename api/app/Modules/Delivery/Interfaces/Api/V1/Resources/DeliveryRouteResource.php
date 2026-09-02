<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tournée (DELIVERY-202, issue #6286) — totaux dénormalisés + stops ordonnés.
 *
 * @mixin \App\Modules\Delivery\Domain\Models\DeliveryRoute
 */
final class DeliveryRouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'route_date' => $this->route_date?->toDateString(),
            'zone' => $this->zone,
            'status' => $this->status,
            'driver_id' => $this->driver_id,
            'vehicle_code' => $this->vehicle_code,
            'deliveries_count' => $this->deliveries_count,
            'delivered_count' => $this->delivered_count,
            'failed_count' => $this->failed_count,
            'cod_collected_minor' => $this->cod_collected_minor,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'stops' => DeliveryRouteStopResource::collection($this->whenLoaded('stops')),
        ];
    }
}
