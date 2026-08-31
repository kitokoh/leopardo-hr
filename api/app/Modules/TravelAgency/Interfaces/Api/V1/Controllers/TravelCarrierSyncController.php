<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelCarrierToken;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Infrastructure\Services\CarrierTripSyncService;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\SyncCarrierTripRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelTripResource;
use Illuminate\Http\JsonResponse;

/**
 * TRAVEL-807 (#6086) — API entrante de synchronisation des trajets
 * transporteurs.
 *
 * Authentifiée par jeton (en-tête `X-Carrier-Token`, hash SHA-256 en base,
 * jamais le jeton en clair) — machine-to-machine, hors Sanctum. L'upsert
 * est idempotent par `external_id` : une synchronisation rejouée ne
 * duplique jamais un trajet. Le tenant est résolu par le jeton (jamais par
 * un paramètre client).
 */
class TravelCarrierSyncController extends Controller
{
    public function upsertTrip(
        SyncCarrierTripRequest $request,
        TenantManager $tenants,
        CarrierTripSyncService $service,
    ): JsonResponse {
        $token = $request->header('X-Carrier-Token', '');

        if ($token === '') {
            abort(401, 'Jeton transporteur manquant (X-Carrier-Token).');
        }

        /** @var TravelCarrierToken|null $carrierToken */
        $carrierToken = TravelCarrierToken::query()
            ->where('token_hash', TravelCarrierToken::hash($token))
            ->where('active', true)
            ->first();

        if (! $carrierToken instanceof TravelCarrierToken) {
            abort(401, 'Jeton transporteur invalide.');
        }

        $company = Company::query()->find($carrierToken->company_id);

        if (! $company instanceof Company) {
            abort(401, 'Tenant introuvable pour ce jeton.');
        }

        $carrierToken->forceFill(['last_used_at' => now()])->save();

        $trip = $tenants->withinTenant($company, fn (): TravelTrip => $service->upsertTrip(
            companyId: $company->id,
            carrierId: (int) $carrierToken->carrier_id,
            payload: $request->validated(),
        ));

        return (new TravelTripResource($trip->load('prices')))->response()->setStatusCode(201);
    }
}
