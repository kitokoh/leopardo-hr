<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CreateBookingAction;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelBookingRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelBookingResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelTripResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-401..404 (#6053..#6056) — Boutique en ligne (v1 : auth tenant).
 *
 * Recherche publique tenant (trajets publiés uniquement, places restantes
 * dérivées de l'inventaire), détail + disponibilité, réservation en ligne
 * (source `online`, expiration 15 min), suivi par référence + code de
 * validation du billet.
 */
class TravelShopController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

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
            ->when($request->query('departure_date'), function ($q, $date): void {
                if (is_string($date)) {
                    $q->whereDate('departure_date', $date);
                }
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
            ->when($request->query('means_of_transport'), fn ($q, $means) => $q->where('means_of_transport', $means))
            ->when($request->query('price_min'), function ($q, $min) {
                $q->whereHas('prices', fn ($price) => $price->where('adult_price_minor', '>=', $min));
            })
            ->when($request->query('price_max'), function ($q, $max) {
                $q->whereHas('prices', fn ($price) => $price->where('adult_price_minor', '<=', $max));
            })
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->paginate($perPage);

        return TravelTripResource::collection($trips)->response();
    }

    public function show(Request $request, TravelTrip $travelTrip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTrip->company_id) {
            abort(404);
        }

        if ($travelTrip->status !== TripStatus::PUBLISHED) {
            abort(404, 'Ce trajet n\'est pas disponible à la vente.');
        }

        $travelTrip->load(['prices', 'route.stops']);

        $data = (new TravelTripResource($travelTrip))->resolve($request);
        $data['available_seats'] = $travelTrip->seats()
            ->where('status', SeatStatus::FREE)
            ->count();

        return response()->json(['data' => $data]);
    }

    public function storeBooking(StoreTravelBookingRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var TravelTrip $trip */
        $trip = TravelTrip::query()->findOrFail($request->validated('trip_id'));

        if ($trip->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($trip->status !== TripStatus::PUBLISHED) {
            abort(409, 'Ce trajet n\'est pas ouvert à la réservation en ligne.');
        }

        $booking = app(CreateBookingAction::class)->execute(
            trip: $trip,
            passengers: $request->validated('passengers'),
            source: BookingSource::ONLINE,
            actor: $actor,
            idempotencyKey: $request->validated('idempotency_key'),
            customerContactId: $request->validated('customer_contact_id'),
        );

        return (new TravelBookingResource($booking))->response()->setStatusCode(201);
    }

    public function track(Request $request, string $reference): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $booking = TravelBooking::query()
            ->where('reference', $reference)
            ->first();

        if (! $booking instanceof TravelBooking || $booking->company_id !== $actor->company_id) {
            abort(404);
        }

        $booking->load('passengers', 'tickets');

        $data = (new TravelBookingResource($booking))->resolve($request);

        // Le code de validation en clair n'est JAMAIS renvoyé : seul le
        // statut et le numéro de billet sont exposés ici.
        $data['ticket_numbers'] = $booking->tickets->map(fn (TravelTicket $t): string => $t->ticket_number);

        return response()->json(['data' => $data]);
    }
}
