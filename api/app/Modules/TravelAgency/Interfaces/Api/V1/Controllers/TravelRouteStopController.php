<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelRouteStop;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelRouteStopRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelRouteStopRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelRouteStopResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-307 (#6037) — Étapes ordonnées d'une route (sous-ressource).
 *
 * `rank` auto-attribué en fin de route quand absent ; réordonnancement
 * transactionnel à la mise à jour ; 404 sûr cross-tenant sur la route ET
 * sur l'étape.
 */
class TravelRouteStopController extends Controller
{
    public function index(Request $request, TravelRoute $travelRoute): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRoute->company_id) {
            abort(404);
        }

        $stops = $travelRoute->stops()->orderBy('rank')->get();

        return TravelRouteStopResource::collection($stops)->response();
    }

    public function store(StoreTravelRouteStopRequest $request, TravelRoute $travelRoute): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRoute->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelRoute)) {
            abort(403);
        }

        $data = $request->validated();

        if (! isset($data['rank'])) {
            $data['rank'] = ($travelRoute->stops()->max('rank') ?? 0) + 1;
        }

        $stop = DB::transaction(function () use ($travelRoute, $data): TravelRouteStop {
            return $travelRoute->stops()->create($data)->refresh();
        });

        return (new TravelRouteStopResource($stop))->response()->setStatusCode(201);
    }

    public function update(
        UpdateTravelRouteStopRequest $request,
        TravelRoute $travelRoute,
        TravelRouteStop $travelRouteStop
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRoute->company_id) {
            abort(404);
        }

        if ($travelRouteStop->company_id !== $travelRoute->company_id
            || $travelRouteStop->route_id !== $travelRoute->id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelRoute)) {
            abort(403);
        }

        $travelRouteStop->update($request->validated());

        // Réordonnancement défensif : les rangs restent une séquence stricte
        // 1..n, sans trou ni doublon, même après un rang arbitraire.
        $this->renumberRanks($travelRoute);

        return (new TravelRouteStopResource($travelRouteStop->refresh()))->response();
    }

    public function destroy(Request $request, TravelRoute $travelRoute, TravelRouteStop $travelRouteStop): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRoute->company_id) {
            abort(404);
        }

        if ($travelRouteStop->company_id !== $travelRoute->company_id
            || $travelRouteStop->route_id !== $travelRoute->id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelRoute)) {
            abort(403);
        }

        DB::transaction(function () use ($travelRouteStop): void {
            $travelRouteStop->delete();
            $route = $travelRouteStop->route;
            assert($route instanceof \App\Modules\TravelAgency\Domain\Models\TravelRoute);
            $this->renumberRanks($route);
        });

        return new JsonResponse(null, 204);
    }

    /**
     * Re-numérote les rangs 1..n dans l'ordre actuel, en une transaction.
     */
    private function renumberRanks(TravelRoute $route): void
    {
        $index = 1;

        $route->stops()->orderBy('rank')->get()->each(function (TravelRouteStop $stop) use (&$index): void {
            $stop->updateQuietly(['rank' => $index]);
            $index++;
        });
    }
}
