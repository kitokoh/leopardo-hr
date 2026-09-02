<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CancelTripAction;
use App\Modules\TravelAgency\Application\Actions\ConnectionSearchAction;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Application\Actions\PublishTripAction;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\CancelTravelTripRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelTripRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelTripRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelPassengerResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelTripResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $query = TravelTrip::query()->with(['prices', 'route.stops']);

        $statusFilter = $request->query('status');
        if (is_string($statusFilter) && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $routeFilter = $request->query('route_id');
        if (is_numeric($routeFilter)) {
            $query->where('route_id', (int) $routeFilter);
        }

        $departureFilter = $request->query('departure_date');
        if (is_string($departureFilter) && $departureFilter !== '') {
            $query->whereDate('departure_date', $departureFilter);
        }

        $trips = $query
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

        // TRAVEL-804 (#6095) — recherche flexible : fenêtre de dates ± N jours
        // autour de `departure_date` (bornée 0..7), résultats triés par prix
        // croissant puis date (le jour le moins cher d'abord — le groupement
        // visuel par date reste côté client).
        $flexibleDays = max(0, min(7, (int) $request->query('flexible_days', 0)));
        $rawDepartureDate = $request->query('departure_date');
        $departureDate = is_string($rawDepartureDate) && $rawDepartureDate !== '' ? $rawDepartureDate : null;
        $flexibleWindow = null;

        if ($departureDate !== null && $flexibleDays > 0) {
            $from = Carbon::parse($departureDate)->subDays($flexibleDays)->toDateString();
            $to = Carbon::parse($departureDate)->addDays($flexibleDays)->toDateString();
            $flexibleWindow = ['from' => $from, 'to' => $to];
        }

        $query = TravelTrip::query()->with(['prices', 'route.stops']);

        $originCityId = $request->query('origin_city_id');
        if (is_numeric($originCityId)) {
            $query->whereHas('route', fn ($route) => $route->where('origin_city_id', (int) $originCityId));
        }

        $destinationCityId = $request->query('destination_city_id');
        if (is_numeric($destinationCityId)) {
            $query->whereHas('route', fn ($route) => $route->where('destination_city_id', (int) $destinationCityId));
        }

        if ($departureDate !== null && $flexibleWindow !== null) {
            $query->whereBetween('departure_date', [$flexibleWindow['from'], $flexibleWindow['to']]);
        } elseif ($departureDate !== null) {
            $query->whereDate('departure_date', $departureDate);
        }

        $dateFrom = $request->query('date_from');
        if (is_string($dateFrom) && $dateFrom !== '') {
            $query->whereDate('departure_date', '>=', $dateFrom);
        }

        $dateTo = $request->query('date_to');
        if (is_string($dateTo) && $dateTo !== '') {
            $query->whereDate('departure_date', '<=', $dateTo);
        }

        $means = $request->query('means_of_transport');
        if (is_string($means) && $means !== '') {
            $query->where('means_of_transport', $means);
        }

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $priceMin = $request->query('price_min');
        if (is_numeric($priceMin)) {
            $query->whereHas('prices', fn ($price) => $price->where('adult_price_minor', '>=', (int) $priceMin));
        }

        $priceMax = $request->query('price_max');
        if (is_numeric($priceMax)) {
            $query->whereHas('prices', fn ($price) => $price->where('adult_price_minor', '<=', (int) $priceMax));
        }

        if ($flexibleDays > 0) {
            // Tri par prix (le moins cher d'abord) puis par date : le
            // « meilleur jour » apparaît en tête des résultats flexibles.
            $query->orderBy(
                TravelTripPrice::query()
                    ->selectRaw('MIN(adult_price_minor)')
                    ->whereColumn('trip_id', 'travel_trips.id'),
                'asc'
            );
        }

        $trips = $query
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->paginate($perPage);

        if ($flexibleWindow !== null) {
            $trips->appends([
                'flexible_days' => $flexibleDays,
                'date_from' => $flexibleWindow['from'],
                'date_to' => $flexibleWindow['to'],
            ]);
        }

        return TravelTripResource::collection($trips)->response();
    }

    /**
     * TRAVEL-809 (#6099) — Correspondances (recherche multi-trajets).
     */
    public function connections(Request $request, ConnectionSearchAction $action): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelTrip::class)) {
            abort(403);
        }

        $origin = $request->integer('origin_city_id');
        $destination = $request->integer('destination_city_id');
        $date = $request->query('date') ? (string) $request->query('date') : null;

        if ($origin <= 0 || $destination <= 0 || $date === null) {
            abort(422, 'origin_city_id, destination_city_id et date sont requis.');
        }

        $results = $action->search($origin, $destination, $date);

        return response()->json(['data' => array_map(function (array $result): array {
            return [
                'total_price_minor' => $result['total_price_minor'],
                'connection_minutes' => $result['connection_minutes'],
                'first' => new TravelTripResource($result['first']),
                'second' => new TravelTripResource($result['second']),
            ];
        }, $results)]);
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
