<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CancelTripAction;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Application\Actions\PublishTripAction;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\CancelTravelTripRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelTripRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelTripRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelPassengerResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelTripResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-308 (#6038) — CRUD des trajets dates (+ generation des sieges).
 * TRAVEL-310 (#6040) — Publication / annulation (transitions validees + outbox).
 * TRAVEL-311 (#6041) — Recherche interne multi-filtres.
 *
 * Meme schema cross-tenant que les autres controleurs du module : 404 sur,
 * jamais 403 sur la ressource elle-meme.
 */
class TravelTripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelTrip::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $trips = TravelTrip::query()
            ->with(['prices', 'route.stops'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('route_id'), fn ($q, $routeId) => $q->where('route_id', $routeId))
            ->when($request->query('departure_date'), fn ($q, $date) => $q->whereDate('departure_date', (string) $date))
            ->orderByDesc('departure_date')
            ->orderBy('departure_time')
            ->paginate($perPage);

        return TravelTripResource::collection($trips)->response();
    }

    public function store(StoreTravelTripRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelTrip::class)) {
            abort(403);
        }

        $trip = DB::transaction(function () use ($request, $actor): TravelTrip {
            $trip = TravelTrip::query()->create([
                ...$request->validated(),
                'created_by_user_id' => $actor->id,
            ]);

            // TRAVEL-208 (#6021) : inventaire transactionnel des sieges.
            app(GenerateTripSeatsAction::class)->execute($trip);

            return $trip->refresh();
        });

        return (new TravelTripResource($trip->load('prices')))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelTrip $travelTrip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTrip->company_id) {
            abort(404);
        }

        $travelTrip->load(['prices', 'route.stops']);

        return (new TravelTripResource($travelTrip))->response();
    }

    public function update(UpdateTravelTripRequest $request, TravelTrip $travelTrip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTrip->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelTrip)) {
            abort(403);
        }

        // Un trajet publie est verrouille : toute modification repasse par le
        // workflow (cancel → mise a jour → republish). Invariant TRAVEL-310.
        if ($travelTrip->status === TripStatus::PUBLISHED) {
            abort(422, 'Un trajet publie ne peut pas etre modifie directement. Annulez-le puis republiez-le.');
        }

        $travelTrip->update($request->validated());

        return (new TravelTripResource($travelTrip->load('prices')))->response();
    }

    public function destroy(Request $request, TravelTrip $travelTrip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTrip->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelTrip)) {
            abort(403);
        }

        $travelTrip->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * TRAVEL-310 (#6040) — Publication d'un trajet.
     */
    public function publish(Request $request, TravelTrip $travelTrip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTrip->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelTrip)) {
            abort(403);
        }

        app(PublishTripAction::class)->execute($travelTrip, $actor);

        return (new TravelTripResource($travelTrip->refresh()->load('prices')))->response();
    }

    /**
     * TRAVEL-310 (#6040) — Annulation d'un trajet (motif obligatoire).
     */
    public function cancel(CancelTravelTripRequest $request, TravelTrip $travelTrip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTrip->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelTrip)) {
            abort(403);
        }

        app(CancelTripAction::class)->execute($travelTrip, $actor, $request->validated('reason'));

        return (new TravelTripResource($travelTrip->refresh()->load('prices')))->response();
    }

    /**
     * TRAVEL-311 (#6041) — Recherche interne back-office.
     *
     * Filtres : origin_city_id / destination_city_id (via la route),
     * departure_date ou plage [date_from, date_to], means_of_transport,
     * status, prix min/max (presence d'un tarif dans la fourchette).
     */
    public function search(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelTrip::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $trips = TravelTrip::query()
            ->with(['prices', 'route.stops'])
            ->when($request->query('origin_city_id'), function ($q, $cityId) {
                $q->whereHas('route', fn ($route) => $route->where('origin_city_id', $cityId));
            })
            ->when($request->query('destination_city_id'), function ($q, $cityId) {
                $q->whereHas('route', fn ($route) => $route->where('destination_city_id', $cityId));
            })
            ->when($request->query('departure_date'), fn ($q, $date) => $q->whereDate('departure_date', (string) $date))
            ->when($request->query('date_from'), fn ($q, $date) => $q->whereDate('departure_date', '>=', (string) $date))
            ->when($request->query('date_to'), fn ($q, $date) => $q->whereDate('departure_date', '<=', (string) $date))
            ->when($request->query('means_of_transport'), fn ($q, $means) => $q->where('means_of_transport', $means))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
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

    /**
     * TRAVEL-318 (#6048) — Manifeste des passagers d'un trajet.
     *
     * Passagers des reservations confirmees (ou au-dela), tries par siege.
     * PII restreinte : jamais de n° de piece d'identite (TravelPassengerResource).
     */
    public function manifest(Request $request, TravelTrip $travelTrip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTrip->company_id) {
            abort(404);
        }

        $passengers = TravelPassenger::query()
            ->whereHas('booking', fn ($booking) => $booking->where('trip_id', $travelTrip->id))
            ->orderBy('seat_number')
            ->get();

        return TravelPassengerResource::collection($passengers)->response();
    }
}
