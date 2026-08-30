<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\BookQuoteAction;
use App\Modules\TravelAgency\Application\Actions\CreateQuoteAction;
use App\Modules\TravelAgency\Domain\Models\TravelQuote;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelQuoteRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelBookingResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelQuoteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-803 (#6094) — Devis & réservations de groupe / corporate.
 */
class TravelQuoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelQuote::class)) {
            abort(403);
        }

        $query = TravelQuote::query();

        $statusFilter = $request->query('status');
        if (is_string($statusFilter) && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $quotes = $query
            ->orderByDesc('created_at')
            ->paginate(max(1, min(1000, (int) $request->query('per_page', 50))));

        return TravelQuoteResource::collection($quotes)->response();
    }

    public function store(StoreTravelQuoteRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelQuote::class)) {
            abort(403);
        }

        $trip = TravelTrip::query()->findOrFail((int) $request->validated('trip_id'));

        $quote = app(CreateQuoteAction::class)->execute(
            trip: $trip,
            passengers: $request->validated('passengers'),
            actor: $actor,
            idempotencyKey: (string) $request->validated('idempotency_key'),
            customerContactId: $request->validated('customer_contact_id') !== null
                ? (int) $request->validated('customer_contact_id')
                : null,
        );

        return (new TravelQuoteResource($quote))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelQuote $travelQuote): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelQuote->company_id) {
            abort(404);
        }

        return (new TravelQuoteResource($travelQuote))->response();
    }

    public function book(Request $request, TravelQuote $travelQuote): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelQuote->company_id) {
            abort(404);
        }

        if ($actor->cannot('create', TravelQuote::class)) {
            abort(403);
        }

        $booking = app(BookQuoteAction::class)->execute($travelQuote, $actor);

        return (new TravelBookingResource($booking))->response();
    }
}
