<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Resources;

use App\Modules\Vtc\Domain\Models\VtcRide;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Course VTC (BC-34 VTC, VTC-03/#8359) — allowlistée.
 *
 * @mixin VtcRide
 */
final class VtcRideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'passenger_name' => $this->passenger_name,
            'passenger_phone' => $this->passenger_phone,
            'pickup' => [
                'latitude' => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
                'address' => $this->pickup_address,
            ],
            'dropoff' => [
                'latitude' => $this->dropoff_latitude,
                'longitude' => $this->dropoff_longitude,
                'address' => $this->dropoff_address,
            ],
            'driver_id' => $this->driver_id,
            'fare_profile_id' => $this->fare_profile_id,
            'estimated_distance_m' => $this->estimated_distance_m,
            'estimated_duration_s' => $this->estimated_duration_s,
            'estimated_price_minor' => $this->estimated_price_minor,
            'final_price_minor' => $this->final_price_minor,
            'currency' => $this->currency,
            'cancel_reason' => $this->cancel_reason,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'expired_at' => $this->expired_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
