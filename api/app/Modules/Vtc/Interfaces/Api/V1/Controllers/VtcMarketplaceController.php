<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Vtc\Infrastructure\Services\VtcMarketplaceService;
use App\Modules\Vtc\Interfaces\Api\V1\Resources\VtcMarketplaceOperatorResource;
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
 */
final class VtcMarketplaceController extends Controller
{
    public function __construct(
        private readonly VtcMarketplaceService $marketplace,
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
}
