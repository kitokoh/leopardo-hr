<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Models\VtcRide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offres de dispatch du chauffeur (BC-34 VTC, VTC-05/#8361).
 *
 * `GET /v1/vtc/driver/offers` : courses `dispatching` dont l'offre COURANTE
 * (metadata.pending_offer, source de vérité du dispatch VTC-04) appartient
 * au chauffeur — polling v1 (push temps réel hors scope, spec §5.3).
 */
final class VtcDriverOfferController extends VtcDriverBaseController
{
    public function index(Request $request): JsonResponse
    {
        $driver = $this->resolveDriver($request);

        $offers = VtcRide::query()
            ->where('status', VtcRideStatus::Dispatching->value)
            ->orderBy('requested_at')
            ->get()
            ->filter(static fn (VtcRide $ride): bool => ($ride->metadata['pending_offer']['driver_id'] ?? null) === $driver->id)
            ->map(static fn (VtcRide $ride): array => [
                'ride_id' => $ride->id,
                'reference' => $ride->reference,
                'pickup' => [
                    'latitude' => $ride->pickup_latitude,
                    'longitude' => $ride->pickup_longitude,
                    'address' => $ride->pickup_address,
                ],
                'dropoff' => [
                    'latitude' => $ride->dropoff_latitude,
                    'longitude' => $ride->dropoff_longitude,
                    'address' => $ride->dropoff_address,
                ],
                'estimated_distance_m' => $ride->estimated_distance_m,
                'estimated_duration_s' => $ride->estimated_duration_s,
                'estimated_price_minor' => $ride->estimated_price_minor,
                'currency' => $ride->currency,
                'offer_seq' => $ride->metadata['pending_offer']['seq'] ?? null,
                'expires_at' => $ride->metadata['pending_offer']['expires_at'] ?? null,
            ])
            ->values();

        return response()->json(['data' => $offers->all()]);
    }
}
