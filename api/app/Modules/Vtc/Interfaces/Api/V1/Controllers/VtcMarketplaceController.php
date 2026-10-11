<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Vtc\Application\Actions\EstimateRideFareAction;
use App\Modules\Vtc\Application\DTOs\VtcFareEstimate;
use App\Modules\Vtc\Infrastructure\Services\VtcMarketplaceService;
use App\Modules\Vtc\Interfaces\Api\V1\Requests\MarketplaceEstimateRequest;
use App\Modules\Vtc\Interfaces\Api\V1\Resources\VtcMarketplaceOperatorResource;
use App\Shared\Geo\GeoPoint;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * #8423 (Phase 2) — API publique MARKETPLACE VTC/taxi.
 *
 * Calquée sur `TravelMarketplaceController` (#7737) : agrégation
 * cross-tenant BORNÉE aux sociétés opt-in (annuaire public
 * `vtc_marketplace_operators`, M-10 + feature `vtc` + société active),
 * tenant résolu PAR RESSOURCE (slug opérateur, référence de course) puis
 * posé via le socle BOS-050 `PublicTenantResolver::withinTenant()`
 * (marqueur `tenant_scope_required`, fail-closed), throttling
 * `shop-public` sur tout le groupe, DTO publics stricts (aucun
 * identifiant interne, aucune metadata).
 *
 * `GET /public/vtc/marketplace/operators` : annuaire public des opérateurs
 * VTC opt-in — identité publique uniquement (slug, nom, ville, devise).
 *
 * `POST /public/vtc/marketplace/estimate` : devis public — wrapper de
 * l'action métier existante `EstimateRideFareAction` (BC-35), exécutée dans
 * le tenant de l'opérateur résolu par slug ; grille tarifaire appliquée
 * côté serveur (jamais de grille interne exposée, `fare_profile_id` et
 * coefficient routier restent internes — `price_minor` vaut null,
 * honnêtement, quand l'opérateur n'a pas de grille).
 */
final class VtcMarketplaceController extends Controller
{
    public function __construct(
        private readonly VtcMarketplaceService $marketplace,
        private readonly PublicTenantResolver $publicTenants,
        private readonly EstimateRideFareAction $estimateFare,
    ) {}

    /**
     * Annuaire public des opérateurs VTC opt-in (M-11), trié par nom.
     */
    public function operators(): AnonymousResourceCollection
    {
        $operators = $this->marketplace->eligibleOperators()
            ->sortBy(fn (Company $company): string => mb_strtolower($company->name))
            ->values();

        return VtcMarketplaceOperatorResource::collection($operators);
    }

    /**
     * Devis public d'une course (M-12) : l'opérateur est résolu par slug
     * (404 uniforme si non opt-in / module inactif), puis le calcul est
     * délégué à `EstimateRideFareAction` dans SON tenant — aucune logique
     * de tarification dupliquée (GEO_SINGLE_USAGE_STRICT : la distance
     * reste calculée par le core geo via l'action).
     */
    public function estimate(MarketplaceEstimateRequest $request): JsonResponse
    {
        /** @var array{operator: string, pickup: array<string, mixed>, dropoff: array<string, mixed>} $validated */
        $validated = $request->validated();

        $company = $this->marketplace->operatorBySlug($validated['operator']);

        $estimate = $this->publicTenants->withinTenant(
            $company,
            fn (): VtcFareEstimate => $this->estimateFare->execute(
                GeoPoint::fromArray($validated['pickup']),
                GeoPoint::fromArray($validated['dropoff']),
            ),
        );

        // DTO strict : distance, durée et prix en minor units + devise —
        // `price_minor` null quand l'opérateur n'a pas de grille (devis
        // honnête, même contrat que l'API passager authentifiée).
        return response()->json([
            'data' => [
                'distance_m' => $estimate->distanceMeters,
                'duration_s' => $estimate->durationSeconds,
                'price_minor' => $estimate->priceMinor,
                'currency' => $estimate->currency,
            ],
        ]);
    }
}
