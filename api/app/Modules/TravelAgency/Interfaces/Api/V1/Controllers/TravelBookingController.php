<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CancelBookingAction;
use App\Modules\TravelAgency\Application\Actions\ConfirmBookingAction;
use App\Modules\TravelAgency\Application\Actions\CreateBookingAction;
use App\Modules\TravelAgency\Application\Actions\IssueTicketsAction;
use App\Modules\TravelAgency\Application\Actions\RefundBookingAction;
use App\Modules\TravelAgency\Application\Actions\RefundPassengerAction;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\CancelTravelBookingRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\RefundTravelPassengerRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelBookingRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelBookingResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelRefundResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelTicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-312..316 (#6042..#6046) — Réservations & billetterie guichet.
 *
 * Création (verrouillage transactionnel des sièges), confirmation comptant,
 * annulation (motif), remboursement (manage), émission des billets. Toutes
 * les mutations passent par des Actions applicatives — jamais d'assignation
 * directe dans le contrôleur.
 */
class TravelBookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelBooking::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $bookings = TravelBooking::query()
            ->with('passengers')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('trip_id'), fn ($q, $tripId) => $q->where('trip_id', $tripId))
            ->when($request->query('reference'), fn ($q, $ref) => $q->where('reference', $ref))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return TravelBookingResource::collection($bookings)->response();
    }

    public function store(StoreTravelBookingRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelBooking::class)) {
            abort(403);
        }

        /** @var TravelTrip $trip */
        $trip = TravelTrip::query()->findOrFail($request->validated('trip_id'));

        if ($trip->company_id !== $actor->company_id) {
            abort(404);
        }

        $booking = app(CreateBookingAction::class)->execute(
            trip: $trip,
            passengers: $request->validated('passengers'),
            source: BookingSource::from($request->validated('booking_source')),
            actor: $actor,
            idempotencyKey: $request->validated('idempotency_key'),
            customerContactId: $request->validated('customer_contact_id'),
        );

        return (new TravelBookingResource($booking))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelBooking $travelBooking): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelBooking->company_id) {
            abort(404);
        }

        $travelBooking->load('passengers', 'tickets');

        return (new TravelBookingResource($travelBooking))->response();
    }

    public function confirm(Request $request, TravelBooking $travelBooking): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelBooking->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelBooking)) {
            abort(403);
        }

        $booking = app(ConfirmBookingAction::class)->execute($travelBooking, $actor);

        return (new TravelBookingResource($booking))->response();
    }

    public function cancel(CancelTravelBookingRequest $request, TravelBooking $travelBooking): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelBooking->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelBooking)) {
            abort(403);
        }

        $booking = app(CancelBookingAction::class)->execute(
            $travelBooking,
            $actor,
            $request->validated('reason'),
        );

        return (new TravelBookingResource($booking))->response();
    }

    public function refund(CancelTravelBookingRequest $request, TravelBooking $travelBooking): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelBooking->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelBooking)) {
            abort(403);
        }

        $booking = app(RefundBookingAction::class)->execute(
            $travelBooking,
            $actor,
            $request->validated('reason'),
        );

        return (new TravelBookingResource($booking))->response();
    }

    /**
     * TRAVEL-808 (#6098) — Remboursement partiel d'un passager.
     */
    public function refundPassenger(RefundTravelPassengerRequest $request, TravelBooking $travelBooking): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelBooking->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelBooking)) {
            abort(403);
        }

        /** @var TravelPassenger $passenger */
        $passenger = TravelPassenger::query()->findOrFail((int) $request->validated('passenger_id'));

        $refund = app(RefundPassengerAction::class)->execute(
            booking: $travelBooking,
            passenger: $passenger,
            actor: $actor,
            reason: (string) $request->validated('reason'),
            refundKey: (string) $request->validated('refund_key'),
        );

        return (new TravelRefundResource($refund))->response()->setStatusCode(201);
    }

    public function issueTickets(Request $request, TravelBooking $travelBooking): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelBooking->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelBooking)) {
            abort(403);
        }

        $tickets = app(IssueTicketsAction::class)->execute($travelBooking, $actor);

        return TravelTicketResource::collection($tickets)->response();
    }
}
