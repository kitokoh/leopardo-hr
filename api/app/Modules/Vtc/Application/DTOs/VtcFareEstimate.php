<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\DTOs;

/**
 * Estimation de course VTC (BC-34 VTC, VTC-03/#8359).
 *
 * DTO immuable partagé entre EstimateRideFareAction, RequestRideAction
 * (persistance du devis sur la course) et l'API passager (réponse
 * `POST /v1/vtc/rides/estimate`). Montants en minor units.
 */
final class VtcFareEstimate
{
    public function __construct(
        public readonly int $distanceMeters,
        public readonly int $roadDistanceMeters,
        public readonly int $durationSeconds,
        public readonly ?int $priceMinor,
        public readonly string $currency,
        public readonly ?int $fareProfileId,
    ) {}

    /**
     * @return array{distance_m: int, road_distance_m: int, duration_s: int, price_minor: int|null, currency: string, fare_profile_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'distance_m' => $this->distanceMeters,
            'road_distance_m' => $this->roadDistanceMeters,
            'duration_s' => $this->durationSeconds,
            'price_minor' => $this->priceMinor,
            'currency' => $this->currency,
            'fare_profile_id' => $this->fareProfileId,
        ];
    }
}
