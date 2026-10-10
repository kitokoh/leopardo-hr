<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Interfaces\Api\V1\Resources\VtcDriverResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Console de répartition — polling v1 (BC-34 VTC, VTC-06/#8362, rôle
 * vtc.dispatcher).
 *
 *   - `GET /v1/vtc/dispatch/rides` : courses ACTIVES (non terminales) du
 *     tenant, plus récentes d'abord — suivi temps réel du cycle de vie
 *     (statuts, chauffeur assigné, offre courante du dispatch) ;
 *   - `GET /v1/vtc/dispatch/drivers` : chauffeurs du tenant avec statut et
 *     dernière position connue (carte temps réel, front VTC-07).
 *
 * Isolation tenant : scope BelongsToCompany — un dispatcher ne voit
 * JAMAIS les courses/chauffeurs d'un autre tenant.
 */
final class VtcDispatchController
{
    /** Bornage de la console temps réel (polling — jamais de table scan). */
    private const RIDES_LIMIT = 100;

    public function rides(): JsonResponse
    {
        $rides = VtcRide::query()
            ->whereNotIn('status', [
                VtcRideStatus::Completed->value,
                VtcRideStatus::Expired->value,
                VtcRideStatus::Cancelled->value,
            ])
            ->orderByDesc('requested_at')
            ->limit(self::RIDES_LIMIT)
            ->get()
            ->map(static fn (VtcRide $ride): array => [
                'id' => $ride->id,
                'reference' => $ride->reference,
                'status' => $ride->status->value,
                'driver_id' => $ride->driver_id,
                'pending_offer_driver_id' => $ride->metadata['pending_offer']['driver_id'] ?? null,
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
                'estimated_price_minor' => $ride->estimated_price_minor,
                'final_price_minor' => $ride->final_price_minor,
                'currency' => $ride->currency,
                'requested_at' => $ride->requested_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $rides->all()]);
    }

    public function drivers(): AnonymousResourceCollection
    {
        $drivers = VtcDriver::query()
            ->orderBy('name')
            ->limit(500)
            ->get();

        return VtcDriverResource::collection($drivers);
    }
}
