<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Infrastructure\Services\TravelConnectionService;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelTripResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-809 (#6099) — Correspondances (recherche multi-trajets).
 *
 * `GET /travel/shop/connections` : paires (leg1, leg2) compatibles en
 * horaires (arrivée + délai de correspondance ≤ départ). `POST
 * /travel/shop/connections/book` : vente groupée — deux réservations
 * indépendantes (billets séparés) liées par `connection_group_id`.
 */
class TravelConnectionController extends Controller
{
    public function search(Request $request, TravelConnectionService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $data = $request->validate([
            'origin_city_id' => ['required', 'integer'],
            'destination_city_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'min_connection_minutes' => ['sometimes', 'integer', 'min:15', 'max:240'],
        ]);

        $connections = $service->search(
            companyId: $actor->company_id,
            originCityId: (int) $data['origin_city_id'],
            destinationCityId: (int) $data['destination_city_id'],
            date: $data['date'],
            minConnectionMinutes: (int) ($data['min_connection_minutes'] ?? 45),
        );

        return response()->json([
            'data' => array_map(fn (array $c): array => [
                'leg1' => (new TravelTripResource($c['leg1']))->resolve(),
                'leg2' => (new TravelTripResource($c['leg2']))->resolve(),
                'connection_minutes' => $c['connection_minutes'],
                'total_price_minor' => $c['total_price_minor'],
                'currency' => $c['currency'],
            ], $connections),
        ]);
    }

    public function book(Request $request, TravelConnectionService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $data = $request->validate([
            'leg1_trip_id' => ['required', 'integer', 'exists:travel_trips,id'],
            'leg2_trip_id' => ['required', 'integer', 'exists:travel_trips,id'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'leg1_passengers' => ['required', 'array', 'min:1', 'max:20'],
            'leg2_passengers' => ['required', 'array', 'min:1', 'max:20'],
            'leg1_passengers.*.full_name' => ['required', 'string', 'max:160'],
            'leg1_passengers.*.age_category' => ['required', 'string'],
            'leg1_passengers.*.class_id' => ['required', 'integer', 'exists:travel_classes,id'],
            'leg2_passengers.*.full_name' => ['required', 'string', 'max:160'],
            'leg2_passengers.*.age_category' => ['required', 'string'],
            'leg2_passengers.*.class_id' => ['required', 'integer', 'exists:travel_classes,id'],
        ]);

        /** @var TravelTrip $leg1 */
        $leg1 = TravelTrip::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($data['leg1_trip_id']);

        /** @var TravelTrip $leg2 */
        $leg2 = TravelTrip::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($data['leg2_trip_id']);

        $bookings = $service->book(
            leg1: $leg1,
            leg2: $leg2,
            passengersLeg1: $data['leg1_passengers'],
            passengersLeg2: $data['leg2_passengers'],
            actor: $actor,
            idempotencyKey: $data['idempotency_key'],
        );

        return response()->json([
            'data' => [
                'connection_group_id' => $bookings['leg1']->connection_group_id,
                'leg1' => ['id' => $bookings['leg1']->id, 'reference' => $bookings['leg1']->reference, 'status' => $bookings['leg1']->status->value],
                'leg2' => ['id' => $bookings['leg2']->id, 'reference' => $bookings['leg2']->reference, 'status' => $bookings['leg2']->status->value],
            ],
        ])->setStatusCode(201);
    }
}
