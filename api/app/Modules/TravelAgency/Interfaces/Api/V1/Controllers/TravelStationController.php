<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Services\ConnectionDelayResolver;
use App\Modules\TravelAgency\Domain\Models\TravelStation;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelStationRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelStationRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelStationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-302 (#6032) — CRUD des gares/terminaux.
 *
 * Toute résolution d'un `{travelStation}` d'un autre tenant renvoie 404
 * (jamais 403, qui révèlerait l'existence de la ressource) : le contrôle
 * `company_id` précède systématiquement l'appel à `TravelStationPolicy`.
 */
class TravelStationController extends Controller
{
    public function __construct(private readonly ConnectionDelayResolver $delays) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelStation::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $stations = TravelStation::query()
            ->orderBy('name')
            ->paginate($perPage);

        return TravelStationResource::collection($stations)->response();
    }

    public function store(StoreTravelStationRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelStation::class)) {
            abort(403);
        }

        $station = TravelStation::query()->create($request->validated());

        // Le réglage de la gare vient de changer : le cache du résolveur doit
        // l'oublier, sinon une recherche lancée dans la même requête servirait
        // l'ancienne valeur.
        $this->delays->forget((int) $station->city_id);

        return (new TravelStationResource($station))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelStation $travelStation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelStation->company_id) {
            abort(404);
        }

        return (new TravelStationResource($travelStation))->response();
    }

    public function update(UpdateTravelStationRequest $request, TravelStation $travelStation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelStation->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelStation)) {
            abort(403);
        }

        $travelStation->update($request->validated());

        // Invalide l'ancienne ET la nouvelle ville : une gare peut avoir été
        // déplacée, et les deux caches sont alors périmés.
        $this->delays->forget((int) $travelStation->city_id);
        $this->delays->forget();

        return (new TravelStationResource($travelStation))->response();
    }

    public function destroy(Request $request, TravelStation $travelStation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelStation->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelStation)) {
            abort(403);
        }

        $travelStation->delete();

        return new JsonResponse(null, 204);
    }
}
