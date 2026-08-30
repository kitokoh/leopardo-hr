<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CreateRoundTripAction;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Models\TravelRoundTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelRoundTripRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelRoundTripResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-802 (#6093) — Aller-retour combiné.
 *
 * Chaque sens reste une réservation standard : la confirmation, l'annulation
 * ou le remboursement s'effectuent sur la réservation concernée (annulable
 * par sens) — le statut du combo est dérivé à la lecture.
 */
class TravelRoundTripController extends Controller
{
    public function store(StoreTravelRoundTripRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelRoundTrip::class)) {
            abort(403);
        }

        $outbound = TravelTrip::query()->findOrFail((int) $request->validated('outbound_trip_id'));
        $return = TravelTrip::query()->findOrFail((int) $request->validated('return_trip_id'));

        $roundTrip = app(CreateRoundTripAction::class)->execute(
            outboundTrip: $outbound,
            returnTrip: $return,
            passengers: $request->validated('passengers'),
            source: BookingSource::from((string) $request->validated('booking_source')),
            actor: $actor,
            idempotencyKey: (string) $request->validated('idempotency_key'),
        );

        return (new TravelRoundTripResource($roundTrip))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelRoundTrip $travelRoundTrip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelRoundTrip->company_id) {
            abort(404);
        }

        $travelRoundTrip->load(['bookingOutbound.passengers', 'bookingReturn.passengers']);

        return (new TravelRoundTripResource($travelRoundTrip))->response();
    }
}
