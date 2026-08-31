<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\ReservationAction;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantReservationRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Enums\ReservationStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantReservationRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantReservationRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantReservationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-601 (#6206) — Réservations CRUD + check-in/no-show + conflit 409.
 *
 * Création : idempotente (rejeu `idempotency_key` → même réservation),
 * référence RSV- générée, conflit de créneau (±2h sur la même table) → 409
 * (repository existsOverlapping). Transitions : confirm / check-in /
 * no-show / cancel / complete. 404 sûr cross-tenant.
 */
class RestaurantReservationController extends Controller
{
    public function __construct(
        private readonly ReservationAction $action,
        private readonly RestaurantReservationRepositoryInterface $reservations,
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
            ->when($request->has('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->query('branch_id')))
            ->when($request->has('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
            ->when($request->has('date'), fn ($query) => $query->whereDate('reserved_at', (string) $request->query('date')))
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

        $data = $request->validated();

        if (isset($data['idempotency_key'])) {
            $existing = RestaurantReservation::query()
                ->where('company_id', $actor->company_id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing instanceof RestaurantReservation) {
                return (new RestaurantReservationResource($existing))->response();
            }
        }

        // Conflit de créneau : même table, fenêtre ±2h (spec §4.3) → 409.
        if ($this->reservations->existsOverlapping(
            $actor->company_id,
            (int) $data['branch_id'],
            isset($data['table_id']) ? (int) $data['table_id'] : null,
            \Illuminate\Support\Carbon::parse($data['reserved_at']),
            (int) ($data['covers'] ?? 1),
        )) {
            abort(409, 'This table is already reserved on an overlapping slot.');
        }

        $reservation = RestaurantReservation::query()->create([
            'company_id' => $actor->company_id,
            'branch_id' => $data['branch_id'],
            'customer_contact_id' => $data['customer_contact_id'] ?? null,
            'contact_name' => $data['contact_name'],
            'contact_phone' => $data['contact_phone'],
            'reserved_at' => $data['reserved_at'],
            'covers' => $data['covers'] ?? 1,
            'table_id' => $data['table_id'] ?? null,
            'zone_id' => $data['zone_id'] ?? null,
            'status' => ReservationStatus::PENDING->value,
            'deposit_minor' => $data['deposit_minor'] ?? null,
            'notes_redacted' => $data['notes_redacted'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
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

        if (! in_array($restaurantReservation->status, [ReservationStatus::PENDING, ReservationStatus::CONFIRMED], true)) {
            abort(409, 'Only a pending or confirmed reservation can be edited.');
        }

        $restaurantReservation->update($request->validated());

        return (new RestaurantReservationResource($restaurantReservation))->response();
    }

    public function confirm(Request $request, RestaurantReservation $restaurantReservation): JsonResponse
    {
        return $this->transition($request, $restaurantReservation, 'confirm');
    }

    public function checkIn(Request $request, RestaurantReservation $restaurantReservation): JsonResponse
    {
        return $this->transition($request, $restaurantReservation, 'checkIn');
    }

    public function noShow(Request $request, RestaurantReservation $restaurantReservation): JsonResponse
    {
        return $this->transition($request, $restaurantReservation, 'noShow');
    }

    public function cancel(Request $request, RestaurantReservation $restaurantReservation): JsonResponse
    {
        return $this->transition($request, $restaurantReservation, 'cancel');
    }

    private function transition(Request $request, RestaurantReservation $reservation, string $action): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $reservation->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $reservation)) {
            abort(403);
        }

        $reservation = match ($action) {
            'confirm' => $this->action->confirm($actor, $reservation),
            'checkIn' => $this->action->checkIn($actor, $reservation),
            'noShow' => $this->action->noShow($actor, $reservation),
            default => $this->action->cancel($actor, $reservation),
        };

        return (new RestaurantReservationResource($reservation))->response();
    }
}
