<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\BookHotelRoomAction;
use App\Modules\TravelAgency\Domain\Models\TravelHotel;
use App\Modules\TravelAgency\Domain\Models\TravelHotelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelHotelRoom;
use App\Modules\TravelAgency\Domain\Models\TravelHotelRoomInventory;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelHotelBookingRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelHotelRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelHotelRoomRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelHotelInventoryRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelHotelRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelHotelRoomRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelHotelBookingResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelHotelResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelHotelRoomInventoryResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelHotelRoomResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
            || (int) $travelHotelRoom->hotel_id !== $travelHotel->id) {
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
            || (int) $travelHotelRoom->hotel_id !== $travelHotel->id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelHotel)) {
            abort(403);
        }

        $travelHotelRoom->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * M4 — Inventaire d'une chambre sur un intervalle de dates.
     *
     * Les nuits sans ligne d'inventaire sont rendues avec `rooms_total = 1`
     * (capacité par défaut d'une chambre nouvellement cataloguée) et
     * `rooms_booked = 0` : l'appelant voit une grille continue, sans trou.
     */
    public function inventory(Request $request, TravelHotel $travelHotel, TravelHotelRoom $travelHotelRoom): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelHotel->company_id) {
            abort(404);
        }

        if ($travelHotelRoom->company_id !== $travelHotel->company_id
            || (int) $travelHotelRoom->hotel_id !== $travelHotel->id) {
            abort(404);
        }

        $from = (string) $request->query('from', Carbon::today()->toDateString());
        $to = (string) $request->query('to', Carbon::today()->addDays(30)->toDateString());

        return response()->json(['data' => $this->inventoryGrid($travelHotelRoom, $from, $to)]);
    }

    /**
     * Grille d'inventaire continue sur [from, to] inclus.
     *
     * Une nuit sans ligne est rendue avec la capacité par défaut (1) : la
     * grille n'a jamais de trou, et l'appelant n'a pas à interpréter une
     * absence comme « fermé ».
     *
     * @return list<array<string, mixed>>
     */
    private function inventoryGrid(TravelHotelRoom $room, string $from, string $to): array
    {
        $rows = TravelHotelRoomInventory::query()
            ->forRoomRange($room->id, $from, $to)
            ->get()
            ->keyBy(fn (TravelHotelRoomInventory $row): string => $row->stay_date->toDateString());

        $days = [];
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $row = $rows->get($date);

            $days[] = [
                'stay_date' => $date,
                'rooms_total' => $row instanceof TravelHotelRoomInventory ? $row->rooms_total : 1,
                'rooms_booked' => $row instanceof TravelHotelRoomInventory ? $row->rooms_booked : 0,
                'rooms_remaining' => $row instanceof TravelHotelRoomInventory ? $row->remaining() : 1,
                'status' => $row instanceof TravelHotelRoomInventory ? $row->status->value : 'active',
            ];

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * M4 — Ouvre ou ferme un intervalle d'inventaire pour une chambre.
     *
     * `rooms_total = 0` ferme la chambre sur la période. Une baisse de capacité
     * sous le nombre déjà vendu est refusée (422) : elle rendrait l'inventaire
     * incohérent (`rooms_booked > rooms_total`).
     */
    public function updateInventory(
        UpdateTravelHotelInventoryRequest $request,
        TravelHotel $travelHotel,
        TravelHotelRoom $travelHotelRoom
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelHotel->company_id) {
            abort(404);
        }

        if ($travelHotelRoom->company_id !== $travelHotel->company_id
            || (int) $travelHotelRoom->hotel_id !== $travelHotel->id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelHotel)) {
            abort(403);
        }

        $from = Carbon::parse($request->validated('from'))->startOfDay();
        $to = Carbon::parse($request->validated('to'))->startOfDay();
        $roomsTotal = (int) $request->validated('rooms_total');
        $status = (string) ($request->validated('status') ?? 'active');

        DB::transaction(function () use ($travelHotelRoom, $from, $to, $roomsTotal, $status): void {
            $cursor = $from->copy();

            while ($cursor->lte($to)) {
                $date = $cursor->toDateString();

                /** @var TravelHotelRoomInventory|null $row */
                $row = TravelHotelRoomInventory::query()
                    ->where('room_id', $travelHotelRoom->id)
                    ->where('stay_date', $date)
                    ->lockForUpdate()
                    ->first();

                if ($row instanceof TravelHotelRoomInventory) {
                    if ($roomsTotal < $row->rooms_booked) {
                        abort(422, __('travel.hotel.below_sold_rooms', ['date' => $date]));
                    }

                    $row->forceFill(['rooms_total' => $roomsTotal, 'status' => $status])->save();
                } else {
                    TravelHotelRoomInventory::query()->create([
                        'room_id' => $travelHotelRoom->id,
                        'stay_date' => $date,
                        'rooms_total' => $roomsTotal,
                        'rooms_booked' => 0,
                        'status' => $status,
                    ]);
                }

                $cursor->addDay();
            }
        });

        // On rend l'intervalle DEMANDÉ (pas la fenêtre par défaut de `inventory`,
        // qui lit `from`/`to` en query string — absents d'un PUT en JSON).
        return response()->json([
            'data' => $this->inventoryGrid($travelHotelRoom, $from->toDateString(), $to->toDateString()),
        ]);
    }

    /**
     * M4 — Réservation d'une chambre sur un intervalle de dates.
     *
     * Le contrôle de surréservation est porté par `BookHotelRoomAction`
     * (verrou `FOR UPDATE` sur les nuits + 409 si une nuit manque).
     */
    public function storeBooking(
        StoreTravelHotelBookingRequest $request,
        TravelHotel $travelHotel,
        TravelHotelRoom $travelHotelRoom
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelHotel->company_id) {
            abort(404);
        }

        if ($travelHotelRoom->company_id !== $travelHotel->company_id
            || (int) $travelHotelRoom->hotel_id !== $travelHotel->id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelHotel)) {
            abort(403);
        }

        $booking = app(BookHotelRoomAction::class)->execute(
            room: $travelHotelRoom,
            actor: $actor,
            idempotencyKey: (string) $request->validated('idempotency_key'),
            data: [
                'check_in' => (string) $request->validated('check_in'),
                'check_out' => (string) $request->validated('check_out'),
                'rooms' => $request->validated('rooms'),
            ],
        );

        return (new TravelHotelBookingResource($booking))->response()->setStatusCode(201);
    }

    /**
     * M4 — Réservations hôtelières d'une chambre.
     */
    public function bookings(Request $request, TravelHotel $travelHotel, TravelHotelRoom $travelHotelRoom): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelHotel->company_id) {
            abort(404);
        }

        if ($travelHotelRoom->company_id !== $travelHotel->company_id
            || (int) $travelHotelRoom->hotel_id !== $travelHotel->id) {
            abort(404);
        }

        $perPage = max(1, min(200, (int) $request->query('per_page', 50)));

        $bookings = TravelHotelBooking::query()
            ->where('room_id', $travelHotelRoom->id)
            ->orderByDesc('check_in')
            ->paginate($perPage);

        return TravelHotelBookingResource::collection($bookings)->response();
    }
}