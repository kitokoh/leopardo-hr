<?php

declare(strict_types=1);

namespace App\Modules\Geo\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Geo\Domain\Exceptions\SearchableTypeForbiddenException;
use App\Modules\Geo\Infrastructure\Services\SearchableRegistry;
use App\Modules\Geo\Interfaces\Api\V1\Requests\NearestRequest;
use App\Modules\Geo\Interfaces\Api\V1\Resources\NearestResultResource;
use App\Shared\Contracts\Geo\GeoServiceContract;
use App\Shared\Geo\GeoPoint;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Recherche « les plus proches » (GEO-05, issue #8354, BC-34 GEO).
 *
 * `GET /v1/geo/nearest?type=&lat=&lng=&radius_km=&limit=` → liste triée par
 * distance croissante, limitée aux types ENREGISTRÉS dans la registry
 * opt-in `geo.searchables` (fail-closed : un type inconnu lève
 * UnknownSearchableTypeException → 422 GEO_UNKNOWN_SEARCHABLE_TYPE, aucune
 * donnée tenant n'est exposée sans enregistrement explicite, spec §7).
 *
 * #8423 (M-02) — garde d'autorisation par type : un type enregistré avec un
 * « authorizer » (ex. `vtc_driver`, positions des chauffeurs réservées
 * admin/dispatcher) est refusé 403 GEO_SEARCHABLE_TYPE_FORBIDDEN aux autres
 * profils. La garde vit UNIQUEMENT ici (couche HTTP) : les appels internes
 * `nearest()` (dispatch en worker, sans utilisateur authentifié) ne sont
 * jamais bloqués.
 */
final class GeoNearestController
{
    public function __construct(
        private readonly GeoServiceContract $geo,
        private readonly SearchableRegistry $registry,
    ) {}

    public function index(NearestRequest $request): AnonymousResourceCollection
    {
        /** @var array{type: string, lat: numeric-string, lng: numeric-string, radius_km?: numeric-string|null, limit?: int|null} $validated */
        $validated = $request->validated();

        $this->authorizeSearchableType($request, $validated['type']);

        $center = new GeoPoint((float) $validated['lat'], (float) $validated['lng']);

        $results = $this->geo->nearest(
            $validated['type'],
            $center,
            isset($validated['radius_km']) ? (float) $validated['radius_km'] : null,
            $validated['limit'] ?? null,
        );

        return NearestResultResource::collection(collect($results));
    }

    /**
     * Garde par type (#8423, M-02) : si la verticale a restreint le type,
     * l'employé authentifié doit satisfaire l'authorizer — 403 sinon. Un
     * type inconnu lève d'abord la 422 fail-closed (comportement conservé).
     */
    private function authorizeSearchableType(NearestRequest $request, string $type): void
    {
        $authorizer = $this->registry->authorizerFor($type);

        if ($authorizer === null) {
            return;
        }

        $user = $request->user();

        if (! $user instanceof Employee || ! $authorizer($user)) {
            throw new SearchableTypeForbiddenException($type);
        }
    }
}
