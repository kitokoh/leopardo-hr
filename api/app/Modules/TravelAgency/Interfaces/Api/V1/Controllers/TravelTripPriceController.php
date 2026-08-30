<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelTripPriceRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelTripPriceRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelTripPriceResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-309 (#6039) — Tarifs par classe d'un trajet (sous-ressource).
 *
 * Meme schema cross-tenant : 404 sur sur le trajet ET sur le tarif ; les
 * montants restent en unites mineures entieres.
 */
class TravelTripPriceController extends Controller
{
    public function index(Request $request, TravelTrip $travelTrip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTrip->company_id) {
            abort(404);
        }

        $prices = $travelTrip->prices()->orderBy('class_id')->get();

        return TravelTripPriceResource::collection($prices)->response();
    }

    public function store(StoreTravelTripPriceRequest $request, TravelTrip $travelTrip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTrip->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelTrip)) {
            abort(403);
        }

        // Un trajet publie est verrouille (invariant TRAVEL-310).
        if ($travelTrip->status->value === 'published') {
            abort(422, 'Un trajet publie ne peut plus voir ses tarifs modifies.');
        }

        try {
            $price = DB::transaction(fn (): TravelTripPrice => $travelTrip->prices()->create($request->validated())->refresh());
        } catch (QueryException $e) {
            // Unicite (trip, classe) violee → 409 propre, pas de 500.
            if (str_contains($e->getMessage(), 'travel_trip_prices_company_trip_class_unique')) {
                abort(409, 'Un tarif existe deja pour cette classe sur ce trajet.');
            }

            throw $e;
        }

        return (new TravelTripPriceResource($price))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelTrip $travelTrip, TravelTripPrice $travelTripPrice): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTrip->company_id) {
            abort(404);
        }

        if ($travelTripPrice->company_id !== $travelTrip->company_id
            || $travelTripPrice->trip_id !== $travelTrip->id) {
            abort(404);
        }

        return (new TravelTripPriceResource($travelTripPrice))->response();
    }

    public function update(
        UpdateTravelTripPriceRequest $request,
        TravelTrip $travelTrip,
        TravelTripPrice $travelTripPrice
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTrip->company_id) {
            abort(404);
        }

        if ($travelTripPrice->company_id !== $travelTrip->company_id
            || $travelTripPrice->trip_id !== $travelTrip->id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelTrip)) {
            abort(403);
        }

        if ($travelTrip->status->value === 'published') {
            abort(422, 'Un trajet publie ne peut plus voir ses tarifs modifies.');
        }

        try {
            $travelTripPrice->update($request->validated());
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'travel_trip_prices_company_trip_class_unique')) {
                abort(409, 'Un tarif existe deja pour cette classe sur ce trajet.');
            }

            throw $e;
        }

        return (new TravelTripPriceResource($travelTripPrice->refresh()))->response();
    }

    public function destroy(
        Request $request,
        TravelTrip $travelTrip,
        TravelTripPrice $travelTripPrice
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTrip->company_id) {
            abort(404);
        }

        if ($travelTripPrice->company_id !== $travelTrip->company_id
            || $travelTripPrice->trip_id !== $travelTrip->id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelTrip)) {
            abort(403);
        }

        $travelTripPrice->delete();

        return new JsonResponse(null, 204);
    }
}
