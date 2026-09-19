<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelTripResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-DISTRIBUTION (#7641) — surface de LECTURE des distributeurs.
 *
 * Authentifiée par clé distributeur (`X-Distributor-Key`, middleware
 * `travel.distributor:<scope>` qui pose le contexte tenant) — le catalogue
 * n'expose que les voyages PUBLIÉS (même règle que la boutique publique,
 * TravelShopController::search) et le suivi de réservation est un extrait
 * minimal sans PII passager.
 */
class TravelDistributorReadController extends Controller
{
    /**
     * Catalogue des voyages publiés du tenant (scope `catalog.read`).
     */
    public function catalog(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $trips = TravelTrip::query()
            ->with(['prices', 'route.stops'])
            ->where('status', TripStatus::PUBLISHED)
            ->when($request->query('origin_city_id'), function ($q, $cityId) {
                $q->whereHas('route', fn ($route) => $route->where('origin_city_id', $cityId));
            })
            ->when($request->query('destination_city_id'), function ($q, $cityId) {
                $q->whereHas('route', fn ($route) => $route->where('destination_city_id', $cityId));
            })
            ->when($request->query('date_from'), function ($q, $date): void {
                if (is_string($date)) {
                    $q->whereDate('departure_date', '>=', $date);
                }
            })
            ->when($request->query('date_to'), function ($q, $date): void {
                if (is_string($date)) {
                    $q->whereDate('departure_date', '<=', $date);
                }
            })
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->paginate($perPage);

        return TravelTripResource::collection($trips)->response();
    }

    /**
     * Suivi d'une réservation par référence (scope `bookings.read`) —
     * extrait minimal, jamais de PII passager (RGPD, même posture que le
     * portail voyageur #7395).
     */
    public function booking(Request $request, string $reference): JsonResponse
    {
        /** @var TravelBooking|null $booking */
        $booking = TravelBooking::query()
            ->with('trip')
            ->where('reference', $reference)
            ->first();

        if (! $booking instanceof TravelBooking) {
            abort(404);
        }

        return response()->json([
            'data' => [
                'reference' => $booking->reference,
                'status' => $booking->status,
                'payment_status' => $booking->payment_status,
                'passenger_count' => $booking->passenger_count,
                'trip' => $booking->trip !== null ? [
                    'code' => $booking->trip->code,
                    'departure_date' => $booking->trip->departure_date->toDateString(),
                    'departure_time' => $booking->trip->departure_time,
                    'status' => $booking->trip->status,
                ] : null,
            ],
        ]);
    }
}
