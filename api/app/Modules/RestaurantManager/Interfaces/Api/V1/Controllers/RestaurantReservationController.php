<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Exceptions\RestaurantReservationConflictException;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantReservationService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantReservationRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantReservationRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantReservationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * RESTO-601 (#6206) — Réservations : CRUD + transitions + conflit de créneau.
 *
 * La création et la réaffectation de table vérifient le chevauchement de
 * créneau (409, `RestaurantReservationService::assertNoConflict`) — deux
 * réservations ne peuvent pas occuper la même table sur le même créneau.
 */
class RestaurantReservationController extends Controller
{
    public function __construct(
        private readonly RestaurantReservationService $reservations,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantReservation::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $reservations = RestaurantReservation::query()
            ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', (int) $v))
            ->when($request->query('date'), fn ($q, $v) => $q->whereDate('reserved_at', (string) $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', (string) $v))
            ->orderBy('reserved_at')
            ->paginate($perPage);

        return RestaurantReservationResource::collection($reservations)->response();
    }

    public function store(StoreRestaurantReservationRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantReservation::class)) {
            abort(403);
        }

        $reservedAt = Carbon::parse((string) $request->validated('reserved_at'));

        if ($request->filled('table_id')) {
            try {
                $this->reservations->assertNoConflict(
                    $actor->company_id,
                    (int) $request->validated('branch_id'),
                    (int) $request->validated('table_id'),
                    $reservedAt,
                );
            } catch (RestaurantReservationConflictException $exception) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }
        }

        /** @var RestaurantReservation $reservation */
        $reservation = RestaurantReservation::query()->create([
            'company_id' => $actor->company_id,
            'branch_id' => (int) $request->validated('branch_id'),
            'contact_name' => $request->validated('contact_name'),
            'contact_phone' => $request->validated('contact_phone'),
            'reserved_at' => $reservedAt,
            'covers' => (int) $request->validated('covers'),
            'table_id' => $request->validated('table_id'),
            'zone_id' => $request->validated('zone_id'),
            'deposit_minor' => $request->validated('deposit_minor'),
            'notes_redacted' => $request->validated('notes_redacted'),
            'idempotency_key' => $request->validated('idempotency_key'),
        ]);

        return (new RestaurantReservationResource($reservation))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantReservation $restaurantReservation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantReservation->company_id) {
            abort(404);
        }

        return (new RestaurantReservationResource($restaurantReservation))->response();
    }

    public function update(UpdateRestaurantReservationRequest $request, RestaurantReservation $restaurantReservation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantReservation->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantReservation)) {
            abort(403);
        }

        if ($restaurantReservation->status->value !== 'pending') {
            return response()->json(['message' => 'Seule une réservation en attente peut être modifiée.'], 422);
        }

        $reservedAt = $request->filled('reserved_at')
            ? Carbon::parse((string) $request->validated('reserved_at'))
            : $restaurantReservation->reserved_at;

        $tableId = $request->filled('table_id')
            ? (int) $request->validated('table_id')
            : $restaurantReservation->table_id;

        if ($tableId !== null) {
            try {
                $this->reservations->assertNoConflict(
                    $actor->company_id,
                    (int) ($request->validated('branch_id') ?? $restaurantReservation->branch_id),
                    $tableId,
                    $reservedAt,
                    $restaurantReservation->id,
                );
            } catch (RestaurantReservationConflictException $exception) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }
        }

        $restaurantReservation->fill($request->validated());
        $restaurantReservation->reserved_at = $reservedAt;
        $restaurantReservation->save();

        return (new RestaurantReservationResource($restaurantReservation))->response();
    }

    public function confirm(Request $request, RestaurantReservation $restaurantReservation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantReservation->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantReservation)) {
            abort(403);
        }

        try {
            $reservation = $this->reservations->confirm($restaurantReservation, $actor);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantReservationResource($reservation))->response();
    }

    public function checkIn(Request $request, RestaurantReservation $restaurantReservation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantReservation->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantReservation)) {
            abort(403);
        }

        try {
            $reservation = $this->reservations->checkIn($restaurantReservation, $request->filled('table_id') ? (int) $request->input('table_id') : null);
        } catch (RestaurantReservationConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantReservationResource($reservation))->response();
    }

    public function noShow(Request $request, RestaurantReservation $restaurantReservation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantReservation->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantReservation)) {
            abort(403);
        }

        try {
            $reservation = $this->reservations->noShow($restaurantReservation);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantReservationResource($reservation))->response();
    }

    public function cancel(Request $request, RestaurantReservation $restaurantReservation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantReservation->company_id) {
            abort(404);
        }

        if ($actor->cannot('cancel', $restaurantReservation)) {
            abort(403);
        }

        try {
            $result = $this->reservations->cancel($restaurantReservation, $request->input('reason'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => new RestaurantReservationResource($result['reservation']),
            'penalty_minor' => $result['penalty_minor'],
            'refundable_minor' => $result['refundable_minor'],
        ]);
    }

    public function deposit(Request $request, RestaurantReservation $restaurantReservation): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantReservation->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantReservation)) {
            abort(403);
        }

        $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
        ]);

        if ($restaurantReservation->deposit_minor !== null && (int) $restaurantReservation->deposit_minor > 0) {
            return response()->json(['message' => 'Un dépôt existe déjà pour cette réservation.'], 422);
        }

        if (in_array($restaurantReservation->status->value, ['completed', 'cancelled', 'no_show'], true)) {
            return response()->json(['message' => 'Impossible d\'enregistrer un dépôt sur une réservation terminée.'], 422);
        }

        $restaurantReservation->deposit_minor = (int) $request->input('amount_minor');
        $restaurantReservation->save();

        return (new RestaurantReservationResource($restaurantReservation))->response();
    }

    public function availability(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantReservation::class)) {
            abort(403);
        }

        $request->validate([
            'branch_id' => ['required', 'integer'],
            'covers' => ['required', 'integer', 'min:1', 'max:50'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['nullable', 'date_format:H:i'],
            'end' => ['nullable', 'date_format:H:i', 'after:start'],
        ]);

        $date = Carbon::parse((string) $request->query('date'));
        $start = $request->query('start') !== null
            ? $date->copy()->setTimeFromTimeString((string) $request->query('start'))
            : $date->copy()->startOfDay();
        $end = $request->query('end') !== null
            ? $date->copy()->setTimeFromTimeString((string) $request->query('end'))
            : $date->copy()->endOfDay();

        $tables = $this->reservations->availableTables(
            $actor->company_id,
            (int) $request->query('branch_id'),
            (int) $request->query('covers'),
            $start,
            $end,
        );

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'covers' => (int) $request->query('covers'),
                'start' => $start->format('H:i'),
                'end' => $end->format('H:i'),
                'available_tables' => $tables,
                'count' => count($tables),
            ],
        ]);
    }
}
