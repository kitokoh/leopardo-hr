<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CancelBookingAction;
use App\Modules\TravelAgency\Application\Actions\CreateBookingAction;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Infrastructure\Services\TravelCurrencyService;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\CancelTravelShopBookingRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelBookingRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelBookingResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelTripResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        // TRAVEL-804 (#6095) — recherche flexible : dates ±N jours, résultats
        // groupés par date et triés par tarif (borné à 7 jours).
        $flexibleDays = (int) $request->query('flexible_days', 0);

        if ($flexibleDays > 0) {
            return $this->flexibleSearch($request, min(7, $flexibleDays), $perPage);
        }

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

        // TRAVEL-805 (#6096) — affichage multi-devise (conversion pure,
        // les montants canoniques restent en devise de référence).
        $this->convertDisplayPrices($trips, $request, $actor);

        return TravelTripResource::collection($trips)->response();
    }

    /**
     * TRAVEL-805 (#6096) — convertit les tarifs affichés dans la devise
     * demandée (param `currency`), sans toucher aux montants stockés.
     */
    private function convertDisplayPrices(mixed $trips, Request $request, Employee $actor): void
    {
        $currency = $request->query('currency');

        if (! is_string($currency) || $currency === '' || strtoupper($currency) === strtoupper((string) ($actor->company?->currency ?? ''))) {
            return;
        }

        $service = app(TravelCurrencyService::class);

        foreach ($trips as $trip) {
            if (! $trip instanceof TravelTrip) {
                continue;
            }

            foreach ($trip->prices as $price) {
                $price->adult_price_minor = $service->convert(
                    $actor->company_id,
                    (int) $price->adult_price_minor,
                    (string) $price->currency,
                    strtoupper($currency),
                );

                if ($price->child_price_minor !== null) {
                    $price->child_price_minor = $service->convert(
                        $actor->company_id,
                        (int) $price->child_price_minor,
                        (string) $price->currency,
                        strtoupper($currency),
                    );
                }

                $price->currency = strtoupper($currency);
            }
        }
    }

    /**
     * TRAVEL-804 (#6095) — Recherche flexible (±N jours).
     *
     * Élargit la recherche à une fenêtre [date−N, date+N] : les trajets sont
     * GROUPÉS par date de départ (tri par tarif minimum dans chaque groupe),
     * puis les groupes sont triés par date. Borné à N ≤ 7 jours et à
     * `per_page × (N+1)` résultats (plafonné à 200) — pas de lecture sans
     * borne.
     */
    private function flexibleSearch(Request $request, int $flexibleDays, int $perPage): JsonResponse
    {
        $anchor = $request->query('departure_date') ?? $request->query('date_from') ?? now()->toDateString();

        if (! is_string($anchor) || $anchor === '') {
            $anchor = now()->toDateString();
        }

        $from = Carbon::parse($anchor)->subDays($flexibleDays)->toDateString();
        $to = Carbon::parse($anchor)->addDays($flexibleDays)->toDateString();

        $limit = min(200, $perPage * ($flexibleDays + 1));

        $trips = TravelTrip::query()
            ->with(['prices', 'route.stops'])
            ->where('status', TripStatus::PUBLISHED)
            ->whereDate('departure_date', '>=', $from)
            ->whereDate('departure_date', '<=', $to)
            ->when($request->query('origin_city_id'), function ($q, $cityId) {
                $q->whereHas('route', fn ($route) => $route->where('origin_city_id', $cityId));
            })
            ->when($request->query('destination_city_id'), function ($q, $cityId) {
                $q->whereHas('route', fn ($route) => $route->where('destination_city_id', $cityId));
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
            ->limit($limit)
            ->get();

        $groups = $trips
            ->groupBy(fn (TravelTrip $trip): string => $trip->departure_date->toDateString())
            ->map(fn ($group) => $group
                ->sortBy(fn (TravelTrip $trip): int => (int) ($trip->prices->min('adult_price_minor') ?? PHP_INT_MAX))
                ->values()
                ->all())
            ->sortKeys();

        return response()->json([
            'data' => $groups->map(fn ($group, string $date) => [
                'date' => $date,
                'trips' => TravelTripResource::collection($group),
            ])->values(),
            'meta' => [
                'flexible_days' => $flexibleDays,
                'date_from' => $from,
                'date_to' => $to,
            ],
        ]);
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
            contactEmail: $request->validated('contact_email'),
            contactPhone: $request->validated('contact_phone'),
            notifyConsent: (bool) $request->validated('notify_consent', false),
            returnTripId: $request->validated('return_trip_id'),
            returnPassengers: $request->validated('return_passengers'),
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

    /**
     * TRAVEL-702 (#6089) — Annulation en ligne (portail client).
     *
     * Le client prouve la possession du billet via `code` (comparé au hash
     * sha256 `validation_code` des billets de la réservation), fournit un
     * motif, et l'annulation n'est possible que si la réservation est
     * pending/confirmed et le départ dans le futur. Idempotent : une
     * réservation déjà annulée est renvoyée telle quelle (CancelBookingAction).
     */
    public function cancel(CancelTravelShopBookingRequest $request, string $reference): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $booking = TravelBooking::query()
            ->where('reference', $reference)
            ->first();

        if (! $booking instanceof TravelBooking || $booking->company_id !== $actor->company_id) {
            abort(404);
        }

        $booking->load('tickets', 'trip');

        // Preuve de possession : le code fourni doit matcher le hash d'un billet.
        $codeHash = hash('sha256', (string) $request->input('code'));
        $owned = $booking->tickets->contains(
            fn (TravelTicket $ticket): bool => hash_equals((string) $ticket->validation_code, $codeHash)
        );

        abort_if(! $owned, 422, 'TRAVEL_BOOKING_CODE_INVALID');

        // Annulation bornée : départ dans le futur uniquement.
        $departure = $booking->trip?->departure_date;
        abort_if($departure !== null && ! $departure->isFuture(), 422, 'TRAVEL_BOOKING_DEPARTURE_PAST');

        $cancelled = app(CancelBookingAction::class)->execute(
            $booking,
            $actor,
            (string) $request->input('reason')
        );

        return response()->json([
            'data' => (new TravelBookingResource($cancelled))->resolve($request),
        ]);
    }
}
