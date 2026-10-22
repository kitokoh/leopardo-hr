<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Vtc\Application\Actions\EstimateRideFareAction;
use App\Modules\Vtc\Application\Actions\RequestRideAction;
use App\Modules\Vtc\Application\DTOs\VtcFareEstimate;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Domain\ValueObjects\IdempotencyKey;
use App\Modules\Vtc\Infrastructure\Services\VtcMarketplaceService;
use App\Modules\Vtc\Interfaces\Api\V1\Requests\MarketplaceEstimateRequest;
use App\Modules\Vtc\Interfaces\Api\V1\Requests\MarketplaceRideStoreRequest;
use App\Modules\Vtc\Interfaces\Api\V1\Resources\VtcMarketplaceOperatorResource;
use App\Modules\Vtc\Interfaces\Api\V1\Resources\VtcMarketplaceRideResource;
use App\Modules\Vtc\Interfaces\Api\V1\Resources\VtcMarketplaceRideStatusResource;
use App\Shared\Geo\GeoPoint;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        private readonly RequestRideAction $requestRide,
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
     * Fiche publique d'un opérateur VTC (P1-02) : identité publique servie par
     * le MÊME DTO que l'annuaire (`VtcMarketplaceOperatorResource`), résolue
     * par slug via `VtcMarketplaceService::operatorBySlug()` — donc avec
     * EXACTEMENT le même périmètre fail-closed que l'annuaire (opt-in
     * `active`, feature `vtc` active, société ni `suspended` ni `expired`).
     *
     * Conséquence voulue : un opérateur dépublié disparaît de l'annuaire ET
     * de sa fiche, sans divergence possible entre les deux surfaces — aucune
     * règle de publication n'est ré-implémentée ici.
     *
     * 404 uniforme (jamais 403) : slug inconnu, dépublié ou hors périmètre sont
     * indiscernables — aucune énumération de profils possible.
     */
    public function operator(string $slug): VtcMarketplaceOperatorResource
    {
        return new VtcMarketplaceOperatorResource($this->marketplace->operatorBySlug($slug));
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

        // DTO strict : distance à vol d'oiseau ET distance routière (base
        // de tarification — #8464 : le prix est calculé sur
        // `roadDistanceMeters`, l'afficher évite l'écart affichage/facture),
        // durée et prix en minor units + devise — `price_minor` null quand
        // l'opérateur n'a pas de grille (devis honnête, même contrat que
        // l'API passager authentifiée).
        return response()->json([
            'data' => [
                'distance_m' => $estimate->distanceMeters,
                'road_distance_m' => $estimate->roadDistanceMeters,
                'duration_s' => $estimate->durationSeconds,
                'price_minor' => $estimate->priceMinor,
                'currency' => $estimate->currency,
            ],
        ]);
    }

    /**
     * Réservation INVITÉE d'une course (M-13) : tenant résolu par slug
     * opérateur, puis délégation à `RequestRideAction` (BC-35 — création
     * idempotente, devis persisté, journal, événement `VtcRideRequested`
     * qui déclenche le dispatch existant ; `passenger_user_id` null —
     * invité — est déjà supporté par toute la chaîne).
     *
     * Contrat d'idempotence (pattern HOSP-006/#7948) : 201 + `tracking_code`
     * en clair à la CRÉATION (présenté une seule fois — seul son hash
     * SHA-256 est persisté) ; 200 au rejeu (même clé Idempotency-Key) avec
     * le MÊME corps de course, SANS le code (present-once BOS-050).
     */
    public function storeRide(MarketplaceRideStoreRequest $request): JsonResponse
    {
        /** @var array{operator: string, idempotency_key: string, pickup: array<string, mixed>, dropoff: array<string, mixed>, pickup_address?: string|null, dropoff_address?: string|null, passenger_name: string, passenger_phone: string} $validated */
        $validated = $request->validated();

        $company = $this->marketplace->operatorBySlug($validated['operator']);

        /** @var array{0: \App\Modules\Vtc\Domain\Models\VtcRide, 1: bool, 2: string|null} $result */
        $result = $this->publicTenants->withinTenant(
            $company,
            fn (): array => $this->requestRide->execute(
                [
                    'passenger_user_id' => null,
                    'passenger_name' => $validated['passenger_name'],
                    'passenger_phone' => $validated['passenger_phone'],
                    'pickup_address' => $validated['pickup_address'] ?? null,
                    'dropoff_address' => $validated['dropoff_address'] ?? null,
                    // Grille : toujours la grille par défaut de l'opérateur —
                    // jamais un fare_profile_id client (config interne).
                    'fare_profile_id' => null,
                ],
                GeoPoint::fromArray($validated['pickup']),
                GeoPoint::fromArray($validated['dropoff']),
                IdempotencyKey::fromString($validated['idempotency_key']),
            ),
        );

        [$ride, $replayed, $trackingCode] = $result;

        /** @var array<string, mixed> $data */
        $data = (new VtcMarketplaceRideResource($ride))->resolve($request);

        if (! $replayed && $trackingCode !== null) {
            $data['tracking_code'] = $trackingCode;
        }

        return response()->json(['data' => $data], $replayed ? 200 : 201);
    }

    /**
     * Suivi invité d'une course (M-14) : référence + code de suivi
     * (query `?code=`). Paramètre absent → 422 (défaut de forme) ; code
     * FOURNI invalide, référence inconnue ou course hors marketplace →
     * 404 uniforme (anti-énumération — jamais de distinction entre
     * « référence inconnue » et « mauvais code », pattern #7395). DTO
     * strict minimal : statut + ETA approximatif, JAMAIS de position
     * chauffeur.
     */
    public function trackRide(Request $request, string $reference): JsonResponse
    {
        $code = $request->query('code', '');
        $code = is_string($code) ? trim($code) : '';

        if ($code === '') {
            abort(422, __('vtc.marketplace.tracking_code_required'));
        }

        return $this->marketplace->withinRide(
            $reference,
            $code,
            fn (VtcRide $ride): JsonResponse => response()->json([
                'data' => (new VtcMarketplaceRideStatusResource($ride))->resolve($request),
            ]),
        );
    }
}
