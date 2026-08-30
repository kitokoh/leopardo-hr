<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\SyncCarrierTripsAction;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelCarrierApiKey;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelPartnerKeyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * TRAVEL-807 (#6086) — API entrante transporteurs.
 *
 * - POST /travel/partner-keys : émission d'une clé API (token affiché une
 *   seule fois, hash au repos) par le tenant (travel.manage).
 * - DELETE /travel/partner-keys/{key} : révocation.
 * - POST /travel/partner/sync : upsert idempotent routes/trajets par clé
 *   externe, authentifié par le middleware travel.partner (X-Partner-Key).
 */
class TravelPartnerController extends Controller
{
    public function storePartnerKey(StoreTravelPartnerKeyRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelCarrierApiKey::class)) {
            abort(403);
        }

        $token = 'ptk_'.Str::random(40);
        $apiKey = TravelCarrierApiKey::query()->create([
            'carrier_id' => (int) $request->validated('carrier_id'),
            'api_key_hash' => hash('sha256', $token),
            'label' => $request->validated('label') !== null ? (string) $request->validated('label') : null,
            'enabled' => true,
            'created_by_user_id' => $actor->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $apiKey->id,
                'carrier_id' => $apiKey->carrier_id,
                'label' => $apiKey->label,
                'api_key' => $token,
                'created_at' => $apiKey->created_at,
            ],
        ], 201);
    }

    public function revokePartnerKey(Request $request, TravelCarrierApiKey $travelCarrierApiKey): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelCarrierApiKey->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelCarrierApiKey)) {
            abort(403);
        }

        $travelCarrierApiKey->forceFill(['enabled' => false])->save();

        return new JsonResponse(['status' => 'ok']);
    }

    public function sync(Request $request, SyncCarrierTripsAction $action): JsonResponse
    {
        /** @var TravelCarrier $carrier */
        $carrier = $request->attributes->get('travel_partner_carrier');

        $routesRaw = $request->input('routes');
        $tripsRaw = $request->input('trips');

        if (! is_array($routesRaw) || ! is_array($tripsRaw)) {
            abort(422, 'routes et trips doivent être des tableaux.');
        }

        /** @var list<array{external_ref: string, code?: string|null, origin_city_id?: int|null, destination_city_id?: int|null, distance_km?: int|null, duration_min?: int|null, status?: string|null}> $routes */
        $routes = $routesRaw;

        /** @var list<array{external_ref: string, route_external_ref: string, code?: string|null, departure_date: string, departure_time: string, arrival_date: string, arrival_time: string, means_of_transport?: string|null, total_seats?: int|null, status?: string|null, prices?: list<array{class_code: string, adult_price_minor: int, child_price_minor?: int|null, currency: string}>|null}> $trips */
        $trips = $tripsRaw;

        if (count($routes) > 200 || count($trips) > 200) {
            abort(422, 'Lot trop grand (max 200 routes et 200 trajets par appel).');
        }

        /** @var Company $company */
        $company = $request->attributes->get('travel_partner_company');

        $result = $action->execute($company, $carrier, $routes, $trips);

        return response()->json(['data' => $result]);
    }
}
