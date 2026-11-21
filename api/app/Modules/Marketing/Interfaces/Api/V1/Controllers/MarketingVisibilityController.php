<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Marketing\Domain\Enums\MarketingVisibilityChannel;
use App\Modules\Marketing\Domain\Enums\MarketingVisibilityMetricsSource;
use App\Modules\Marketing\Domain\Models\MarketingVisibilityObjective;
use App\Modules\Marketing\Infrastructure\Services\MarketingOsDashboardService;
use App\Modules\Marketing\Infrastructure\Services\MarketingOsPresenter;
use App\Modules\Marketing\Infrastructure\Services\MarketingPublicationModeResolver;
use App\Modules\Marketing\Infrastructure\Services\PlatformPublishingCapability;
use App\Modules\Marketing\Interfaces\Api\V1\Controllers\Concerns\ResolvesMarketingTenant;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreMarketingVisibilityObjectiveRequest;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marketing OS — ressource VISIBILITÉ (tableau de bord, objectifs SEO/SEA/AEO/GEO,
 * capacités de publication).
 *
 * Extrait de `PlatformMarketingOsController` (1 022 lignes). Deux garanties
 * d'honnêteté portées ici :
 *
 *  - **les statistiques non mesurées sont déclarées indisponibles**, jamais
 *    estimées et présentées comme un fait : chaque objectif expose sa
 *    provenance (`measured` / `estimated` / `unavailable`) ;
 *  - **aucune visibilité n'est garantie** : la matrice des capacités explique
 *    l'état réel au lieu de promettre une automatisation inexistante.
 */
final class MarketingVisibilityController extends Controller
{
    use ResolvesMarketingTenant;

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly MarketingOsDashboardService $dashboard,
        private readonly MarketingPublicationModeResolver $modes,
        private readonly PlatformPublishingCapability $capabilities,
        private readonly MarketingOsPresenter $presenter,
    ) {}

    /** GET /platform/companies/{company}/marketing-os/overview */
    public function overview(string $company): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($target): JsonResponse {
            return new JsonResponse([
                'data' => $this->dashboard->overview((string) $target->id),
                'meta' => [
                    'company_id' => (string) $target->id,
                    'direct_publishing_available' => $this->modes->isDirectAvailable((string) $target->id),
                ],
            ]);
        });
    }

    /**
     * GET /platform/companies/{company}/marketing-os/visibility-objectives
     *
     * Objectifs de visibilité rattachés aux contenus. Chaque ligne expose sa
     * provenance d'indicateur (`measured` / `estimated` / `unavailable`) :
     * l'interface ne peut donc pas présenter une estimation comme une mesure.
     */
    public function indexVisibilityObjectives(Request $request, string $company): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($request): JsonResponse {
            $query = MarketingVisibilityObjective::query()->orderByDesc('id');

            $channel = $request->query('channel');
            if (is_string($channel) && $channel !== '') {
                $query->where('channel', $channel);
            }

            $campaignId = $request->query('campaign_id');
            if (is_numeric($campaignId)) {
                $query->where('campaign_id', (int) $campaignId);
            }

            // Pagination réelle : un `limit` silencieux tronquait la liste à
            // 100 lignes sans le dire, et l'interface ne pouvait ni compter ni
            // atteindre les objectifs suivants. `per_page` est borné par
            // `MAX_PER_PAGE` : sans plafond, un client pouvait demander
            // `per_page=100000` et faire charger toute la table en mémoire.
            $perPage = (int) ($request->query('per_page') ?? self::DEFAULT_PER_PAGE);
            $page = $query->paginate(max(1, min($perPage, self::MAX_PER_PAGE)));

            return new JsonResponse([
                'data' => $page->getCollection()->map(fn (MarketingVisibilityObjective $o): array => $this->presenter->presentVisibilityObjective($o))->all(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'channels' => MarketingVisibilityChannel::values(),
                    'metrics_sources' => MarketingVisibilityMetricsSource::values(),
                    // Rappel explicite : aucune visibilité n'est garantie.
                    'guarantee' => __('marketing.os.visibility_no_guarantee'),
                ],
            ]);
        });
    }

    /** POST /platform/companies/{company}/marketing-os/visibility-objectives */
    public function storeVisibilityObjective(StoreMarketingVisibilityObjectiveRequest $request, string $company): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);
        $payload = $request->validated();

        return $this->tenantManager->withinTenant($target, function () use ($payload, $target): JsonResponse {
            $this->assertCampaignBelongsToTenant($payload['campaign_id'] ?? null);
            $this->assertIdeaBelongsToTenant($payload['content_idea_id'] ?? null);
            $this->assertAssetBelongsToTenant($payload['marketing_asset_id'] ?? null);

            $objective = new MarketingVisibilityObjective;
            $objective->fill($payload);
            $objective->company_id = (string) $target->id;
            $objective->created_by = null; // Acteur plateforme.

            // Un indicateur sans provenance déclarée n'est PAS présenté comme
            // une mesure : on retombe sur `unavailable` (fail-closed).
            if (($payload['metrics'] ?? null) !== null && ($payload['metrics_source'] ?? null) === null) {
                $objective->metrics_source = MarketingVisibilityMetricsSource::Unavailable;
            }

            $objective->save();

            return new JsonResponse([
                'data' => $this->presenter->presentVisibilityObjective($objective->refresh()),
            ], Response::HTTP_CREATED);
        });
    }

    /**
     * GET /platform/companies/{company}/marketing-os/publishing-capabilities
     *
     * Matrice des plateformes et de leurs capacités RÉELLES (#8532) : quelles
     * plateformes autorisent une publication directe, et pour les autres, le
     * motif pour lequel le mode assisté s'applique. L'interface peut ainsi
     * expliquer l'état au lieu de promettre une automatisation inexistante.
     */
    public function publishingCapabilities(string $company): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($target): JsonResponse {
            $companyId = (string) $target->id;
            $directAvailable = $this->modes->isDirectAvailable($companyId);

            return new JsonResponse([
                'data' => $this->capabilities->matrix(),
                'meta' => [
                    'direct_available' => $directAvailable,
                    'mode' => $this->modes->resolve($companyId)->value,
                    'mode_explanation' => $directAvailable
                        ? __('marketing.os.mode_direct_explanation')
                        : __('marketing.os.mode_assisted_explanation'),
                ],
            ]);
        });
    }
}
