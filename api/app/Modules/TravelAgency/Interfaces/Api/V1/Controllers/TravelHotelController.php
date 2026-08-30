<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelHotel;
use App\Modules\TravelAgency\Domain\Models\TravelHotelRoom;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelHotelRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelHotelRoomRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelHotelRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelHotelRoomRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelHotelResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelHotelRoomResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-321 (#6051) — CRUD du catalogue hôtelier (+ chambres en
 * sous-ressource, mêmes conventions que `TravelRouteStopController`).
 *
 * Même schéma cross-tenant que les autres contrôleurs du module : 404 sûr,
 * jamais 403 sur la ressource elle-même. Les chambres sont liées par la
 * route : `hotel_id` n'est jamais accepté dans le body.
 */
class TravelHotelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelHotel::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $hotels = TravelHotel::query()
            ->with('rooms')
            ->when($request->query('city_id'), fn ($query, $cityId) => $query->where('city_id', $cityId))
            ->orderBy('name')
            ->paginate($perPage);

        return TravelHotelResource::collection($hotels)->response();
    }

    public function store(StoreTravelHotelRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelHotel::class)) {
            abort(403);
        }

        $hotel = TravelHotel::query()->create($request->validated());

        return (new TravelHotelResource($hotel->refresh()->load('rooms')))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelHotel $travelHotel): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelHotel->company_id) {
            abort(404);
        }

        return (new TravelHotelResource($travelHotel))->response();
    }

    public function update(UpdateTravelHotelRequest $request, TravelHotel $travelHotel): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelHotel->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelHotel)) {
            abort(403);
        }

        $travelHotel->update($request->validated());

        return (new TravelHotelResource($travelHotel))->response();
    }

    public function destroy(Request $request, TravelHotel $travelHotel): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelHotel->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelHotel)) {
            abort(403);
        }

        $travelHotel->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * TRAVEL-321 (#6051) — Liste des chambres d'un hôtel.
     */
    public function rooms(Request $request, TravelHotel $travelHotel): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelHotel->company_id) {
            abort(404);
        }

        $rooms = $travelHotel->rooms()->orderBy('room_number')->get();

        return TravelHotelRoomResource::collection($rooms)->response();
    }

    /**
     * TRAVEL-321 (#6051) — Création d'une chambre (sous-ressource).
     */
    public function storeRoom(StoreTravelHotelRoomRequest $request, TravelHotel $travelHotel): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelHotel->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelHotel)) {
            abort(403);
        }

        $room = $travelHotel->rooms()->create($request->validated());

        return (new TravelHotelRoomResource($room->refresh()))->response()->setStatusCode(201);
    }

    /**
     * TRAVEL-321 (#6051) — Mise à jour d'une chambre.
     *
     * 404 sûr si la chambre n'appartient pas à l'hôtel de la route ou au
     * même tenant.
     */
    public function updateRoom(
        UpdateTravelHotelRoomRequest $request,
        TravelHotel $travelHotel,
        TravelHotelRoom $travelHotelRoom
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelHotel->company_id) {
            abort(404);
        }

        if ($travelHotelRoom->company_id !== $travelHotel->company_id
            || $travelHotelRoom->hotel_id !== $travelHotel->id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelHotel)) {
            abort(403);
        }

        $travelHotelRoom->update($request->validated());

        return (new TravelHotelRoomResource($travelHotelRoom->refresh()))->response();
    }

    /**
     * TRAVEL-321 (#6051) — Suppression d'une chambre.
     */
    public function destroyRoom(Request $request, TravelHotel $travelHotel, TravelHotelRoom $travelHotelRoom): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelHotel->company_id) {
            abort(404);
        }

        if ($travelHotelRoom->company_id !== $travelHotel->company_id
            || $travelHotelRoom->hotel_id !== $travelHotel->id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelHotel)) {
            abort(403);
        }

        $travelHotelRoom->delete();

        return new JsonResponse(null, 204);
    }
}
