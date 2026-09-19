<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelDistributorKey;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelDistributorKeyRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelDistributorKeyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * TRAVEL-DISTRIBUTION (#7641) — gestion des clés API de lecture distributeurs.
 *
 * - GET  /travel/distributor-keys : liste + stats d'usage (jamais le hash).
 * - POST /travel/distributor-keys : émission (token `dsk_…` affiché UNE fois,
 *   hash SHA-256 au repos — pattern TravelPartnerController/#6086).
 * - POST /travel/distributor-keys/{key}/rotate : rotation — nouveau token
 *   affiché une fois, l'ancien est immédiatement invalide.
 * - POST /travel/distributor-keys/{key}/revoke : révocation (acte métier →
 *   POST, convention #4930) ; la ligne reste pour la traçabilité.
 */
class TravelDistributorKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelDistributorKey::class)) {
            abort(403);
        }

        $keys = TravelDistributorKey::query()->orderByDesc('id')->get();

        return TravelDistributorKeyResource::collection($keys)->response();
    }

    public function store(StoreTravelDistributorKeyRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelDistributorKey::class)) {
            abort(403);
        }

        $token = 'dsk_'.Str::random(40);

        /** @var list<string> $scopes */
        $scopes = array_values(array_unique($request->validated('scopes')));

        $key = TravelDistributorKey::query()->create([
            'name' => (string) $request->validated('name'),
            'api_key_hash' => hash('sha256', $token),
            'scopes' => $scopes,
            'enabled' => true,
            'created_by_user_id' => $actor->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $key->id,
                'name' => $key->name,
                'scopes' => $key->scopes,
                'api_key' => $token,
                'created_at' => $key->created_at,
            ],
        ], 201);
    }

    /**
     * Rotation : nouveau token, l'ancien hash est remplacé atomiquement —
     * le distributeur bascule sans fenêtre où deux tokens sont valides.
     */
    public function rotate(Request $request, TravelDistributorKey $travelDistributorKey): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelDistributorKey->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelDistributorKey)) {
            abort(403);
        }

        if (! $travelDistributorKey->enabled) {
            abort(422, __('travel.distributor_keys.rotate_revoked'));
        }

        $token = 'dsk_'.Str::random(40);
        $travelDistributorKey->forceFill([
            'api_key_hash' => hash('sha256', $token),
            'rotated_at' => now(),
        ])->save();

        return response()->json([
            'data' => [
                'id' => $travelDistributorKey->id,
                'name' => $travelDistributorKey->name,
                'scopes' => $travelDistributorKey->scopes,
                'api_key' => $token,
                'rotated_at' => $travelDistributorKey->rotated_at,
            ],
        ]);
    }

    /**
     * Révocation idempotente : la ligne reste (stats/traçabilité), la clé
     * cesse immédiatement d'authentifier.
     */
    public function revoke(Request $request, TravelDistributorKey $travelDistributorKey): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelDistributorKey->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelDistributorKey)) {
            abort(403);
        }

        if ($travelDistributorKey->enabled) {
            $travelDistributorKey->forceFill([
                'enabled' => false,
                'revoked_at' => now(),
            ])->save();
        }

        return (new TravelDistributorKeyResource($travelDistributorKey))->response();
    }
}
