<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CancelRentalBookingAction;
use App\Modules\TravelAgency\Application\Actions\CreateRentalBookingAction;
use App\Modules\TravelAgency\Domain\Models\TravelRentalBooking;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\CancelTravelRentalBookingRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelRentalBookingRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelRentalBookingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-320 (#6050) — Réservations de location de véhicules.
 *
 * Création avec contrôle de non-chevauchement (Action transactionnelle,
 * 409 en cas de conflit), annulation (motif), liste et détail.
 */
class TravelRentalBookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelRentalBooking::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $bookings = TravelRentalBooking::query()
            ->when($request->query('vehicle_id'), fn ($q, $id) => $q->where('vehicle_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return TravelRentalBookingResource::collection($bookings)->response();
    }

    public function store(StoreTravelRentalBookingRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelRentalBooking::class)) {
            abort(403);
        }

        /** @var TravelRentalVehicle $vehicle */
        $vehicle = TravelRentalVehicle::query()->findOrFail($request->validated('vehicle_id'));

        if ($vehicle->company_id !== $actor->company_id) {
            abort(404);
        }

        $booking = app(CreateRentalBookingAction::class)->execute(
            vehicle: $vehicle,
            actor: $actor,
            idempotencyKey: $request->validated('idempotency_key'),
            data: $request->validated(),
        );

        return (new TravelRentalBookingResource($booking))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelRentalBooking $travelRentalBooking): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRentalBooking->company_id) {
            abort(404);
        }

        return (new TravelRentalBookingResource($travelRentalBooking))->response();
    }

    public function cancel(CancelTravelRentalBookingRequest $request, TravelRentalBooking $travelRentalBooking): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRentalBooking->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelRentalBooking)) {
            abort(403);
        }

        $booking = app(CancelRentalBookingAction::class)->execute(
            $travelRentalBooking,
            $actor,
            $request->validated('reason'),
        );

        return (new TravelRentalBookingResource($booking))->response();
    }
}
