<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Marketing\Application\Actions\PromoteContentIdeaToProduction;
use App\Modules\Marketing\Domain\Enums\ContentIdeaStatus;
use App\Modules\Marketing\Domain\Enums\MarketingAssetKind;
use App\Modules\Marketing\Domain\Enums\MarketingAssetValidationStatus;
use App\Modules\Marketing\Domain\Enums\MarketingCampaignStatus;
use App\Modules\Marketing\Domain\Enums\MarketingContentFormat;
use App\Modules\Marketing\Domain\Enums\MarketingObjective;
use App\Modules\Marketing\Domain\Enums\MarketingPublicationMode;
use App\Modules\Marketing\Domain\Enums\MarketingPublicationStatus;
use App\Modules\Marketing\Domain\Enums\MarketingVisibilityChannel;
use App\Modules\Marketing\Domain\Enums\MarketingVisibilityMetricsSource;
use App\Modules\Marketing\Domain\Models\ContentIdea;
use App\Modules\Marketing\Domain\Models\MarketingAsset;
use App\Modules\Marketing\Domain\Models\MarketingCampaign;
use App\Modules\Marketing\Domain\Models\MarketingPublication;
use App\Modules\Marketing\Domain\Models\MarketingVisibilityObjective;
use App\Modules\Marketing\Infrastructure\Services\MarketingAssetStorage;
use App\Modules\Marketing\Infrastructure\Services\MarketingOsAudit;
use App\Modules\Marketing\Infrastructure\Services\MarketingOsDashboardService;
use App\Modules\Marketing\Infrastructure\Services\MarketingPublicationModeResolver;
use App\Modules\Marketing\Infrastructure\Services\MarketingPublicationStatsService;
use App\Modules\Marketing\Infrastructure\Services\PlatformPublishingCapability;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreContentIdeaRequest;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreMarketingAssetRequest;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreMarketingCampaignRequest;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreMarketingPublicationRequest;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreMarketingVisibilityObjectiveRequest;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\UpdateMarketingPublicationStatusRequest;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marketing OS — surface SUPER ADMIN (brief §A à §J).
 *
 * ## Pourquoi une surface plateforme distincte de la surface tenant
 *
 * Les routes tenant existantes (`routes/modules/marketing.php`) sont réservées
 * au rôle manager marketing d'UNE entreprise (`api.manager:marketing,principal`).
 * L'espace demandé vit dans le Super Admin : il doit donc passer par
 * `auth:super_admin_api` + `platform.permission:marketing_os.manage`, et
 * agir sur le tenant CIBLÉ explicitement (`/platform/companies/{company}/…`).
 *
 * Le précédent est `PlatformFundraisingSettingsController` (#8441) : on résout
 * l'entreprise par `PlatformCompanyLookup::findOrFail()` puis on exécute la
 * logique DANS son contexte avec `TenantManager::withinTenant()`. Toutes les
 * requêtes ci-dessous s'exécutent donc avec le `search_path` du tenant, ce qui
 * active le scope `BelongsToCompany` : l'isolation est garantie par le framework,
 * pas par un `where('company_id', …)` recopié à la main.
 *
 * ## Garanties fonctionnelles tenues par cette classe
 *
 *  - **Une idée se transforme en tâche de production** sans perte de données :
 *    `POST /ideas/{idea}/promote` (contrat partagé, cf. `PromoteContentIdeaToProduction`).
 *  - **Une ressource peut alimenter plusieurs publications** : le lien est porté
 *    par `marketing_publications.marketing_asset_id`, et `GET /assets/{asset}/publications`
 *    répond à « déjà publiée ? où ? quand ? avec quels résultats ? ».
 *  - **Direct vs assisté est toujours explicite** : `mode` est calculé, jamais
 *    deviné par le client, et `direct_available` est renvoyé pour l'expliquer.
 *  - **Aucune publication n'est marquée publiée sans preuve** : la confirmation
 *    exige `published_url` (règle de validation structurelle).
 *  - **Les statistiques non mesurées sont déclarées indisponibles**, jamais
 *    estimées et présentées comme un fait.
 */
final class PlatformMarketingOsController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly MarketingOsDashboardService $dashboard,
        private readonly MarketingAssetStorage $storage,
        private readonly MarketingPublicationModeResolver $modes,
        private readonly MarketingPublicationStatsService $stats,
        private readonly PlatformPublishingCapability $capabilities,
        private readonly PromoteContentIdeaToProduction $promote,
        private readonly MarketingOsAudit $audit,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // A — Tableau de bord
    // ─────────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────────
    // B — Stratégie : campagnes et idées
    // ─────────────────────────────────────────────────────────────────────

    /** GET /platform/companies/{company}/marketing-os/campaigns */
    public function indexCampaigns(Request $request, string $company): JsonResponse
    {
        // Les filtres de statut et d'objectif sont contraints par les
        // enumerations du domaine : une valeur hors vocabulaire est refusee
        // (422) au lieu de produire silencieusement une liste vide, que
        // l'interface afficherait comme « aucun resultat ».
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(MarketingCampaignStatus::values())],
            'sector' => ['nullable', 'string', 'max:60'],
            'objective' => ['nullable', 'string', Rule::in(MarketingObjective::values())],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($validated): JsonResponse {
            $query = MarketingCampaign::query()->orderByDesc('created_at');

            if (filled($validated['status'] ?? null)) {
                $query->where('status', $validated['status']);
            }
            if (filled($validated['sector'] ?? null)) {
                $query->where('sector', $validated['sector']);
            }
            if (filled($validated['objective'] ?? null)) {
                $query->where('objective', $validated['objective']);
            }
            if (filled($validated['search'] ?? null)) {
                $search = trim((string) $validated['search']);
                $pattern = $this->likePattern($search);
                $query->where(function ($inner) use ($pattern): void {
                    $inner->where('name', 'like', $pattern)
                        ->orWhere('description', 'like', $pattern)
                        ->orWhere('product', 'like', $pattern);
                });
            }

            $page = $query->paginate((int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE));

            return new JsonResponse([
                'data' => $page->getCollection()->map(fn (MarketingCampaign $c): array => $this->presentCampaign($c))->all(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ]);
        });
    }

    /** POST /platform/companies/{company}/marketing-os/campaigns */
    public function storeCampaign(StoreMarketingCampaignRequest $request, string $company): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);
        $payload = $request->validated();

        return $this->tenantManager->withinTenant($target, function () use ($payload, $target): JsonResponse {
            $campaign = new MarketingCampaign;
            $campaign->fill($payload);
            $campaign->company_id = (string) $target->id;
            $campaign->save();

            return new JsonResponse(['data' => $this->presentCampaign($campaign->refresh())], Response::HTTP_CREATED);
        });
    }

    /** GET /platform/companies/{company}/marketing-os/ideas */
    public function indexIdeas(Request $request, string $company): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(ContentIdeaStatus::values())],
            'campaign_id' => ['nullable', 'integer', 'min:1'],
            'format' => ['nullable', 'string', Rule::in(MarketingContentFormat::values())],
            'locale' => ['nullable', 'string', 'max:10'],
            'sector' => ['nullable', 'string', 'max:60'],
            'product' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'late_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($validated): JsonResponse {
            $query = ContentIdea::query()->orderByDesc('created_at');

            foreach (['status', 'format', 'locale', 'sector', 'product', 'campaign_id'] as $field) {
                if (filled($validated[$field] ?? null)) {
                    $query->where($field, $validated[$field]);
                }
            }

            if (filled($validated['search'] ?? null)) {
                $search = trim((string) $validated['search']);
                $pattern = $this->likePattern($search);
                $query->where(function ($inner) use ($pattern): void {
                    $inner->where('title', 'like', $pattern)
                        ->orWhere('description', 'like', $pattern)
                        ->orWhere('key_message', 'like', $pattern);
                });
            }

            if (filled($validated['from'] ?? null)) {
                $query->where('created_at', '>=', Carbon::parse($validated['from'])->startOfDay());
            }
            if (filled($validated['to'] ?? null)) {
                $query->where('created_at', '<=', Carbon::parse($validated['to'])->endOfDay());
            }
            if (($validated['late_only'] ?? false) === true) {
                $query->whereNotNull('publish_due_at')
                    ->where('publish_due_at', '<', Carbon::now())
                    ->whereNotIn('status', [ContentIdeaStatus::Published->value, ContentIdeaStatus::Cancelled->value]);
            }

            $page = $query->paginate((int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE));

            return new JsonResponse([
                'data' => $page->getCollection()->map(fn (ContentIdea $i): array => $this->presentIdea($i))->all(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ]);
        });
    }

    /** POST /platform/companies/{company}/marketing-os/ideas */
    public function storeIdea(StoreContentIdeaRequest $request, string $company): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);
        $payload = $request->validated();

        return $this->tenantManager->withinTenant($target, function () use ($payload, $target): JsonResponse {
            $this->assertCampaignBelongsToTenant($payload['campaign_id'] ?? null);

            $idea = new ContentIdea;
            $idea->fill($payload);
            $idea->company_id = (string) $target->id;
            $idea->save();

            return new JsonResponse(['data' => $this->presentIdea($idea->refresh())], Response::HTTP_CREATED);
        });
    }

    /** PATCH /platform/companies/{company}/marketing-os/ideas/{idea} */
    public function updateIdea(StoreContentIdeaRequest $request, string $company, int $idea): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);
        $payload = $request->validated();

        return $this->tenantManager->withinTenant($target, function () use ($payload, $idea): JsonResponse {
            $model = ContentIdea::query()->findOrFail($idea);
            $this->assertCampaignBelongsToTenant($payload['campaign_id'] ?? null);

            $model->fill($payload);
            $model->save();

            return new JsonResponse(['data' => $this->presentIdea($model->refresh())]);
        });
    }

    /** POST /platform/companies/{company}/marketing-os/ideas/{idea}/promote */
    public function promoteIdea(string $company, int $idea): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($idea): JsonResponse {
            $model = ContentIdea::query()->findOrFail($idea);
            $wasLinked = $model->production_task_id !== null;
            $promoted = $this->promote->execute($model);

            // Idempotent côté audit : un rejeu qui ne crée aucune tâche n'est
            // pas un événement métier — il n'écrit rien dans le journal.
            if (! $wasLinked && $promoted->production_task_id !== null) {
                $this->audit->ideaPromoted($promoted, [
                    'production_task_created' => true,
                    'campaign_id' => $promoted->campaign_id,
                ]);
            }

            return new JsonResponse([
                'data' => $this->presentIdea($promoted->refresh()),
                'meta' => [
                    // Un échec de création de tâche n'est pas silencieux : il est
                    // exposé, et l'idée reste promue côté marketing.
                    'production_task_created' => $promoted->production_task_id !== null,
                    'idempotent' => $wasLinked,
                ],
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // C — Médiathèque
    // ─────────────────────────────────────────────────────────────────────

    /** GET /platform/companies/{company}/marketing-os/assets */
    public function indexAssets(Request $request, string $company): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['nullable', 'string', Rule::in(MarketingAssetKind::values())],
            'campaign_id' => ['nullable', 'integer', 'min:1'],
            'content_idea_id' => ['nullable', 'integer', 'min:1'],
            'locale' => ['nullable', 'string', 'max:10'],
            'product' => ['nullable', 'string', 'max:120'],
            'sector' => ['nullable', 'string', 'max:60'],
            'objective' => ['nullable', 'string', Rule::in(MarketingObjective::values())],
            'validation_status' => ['nullable', 'string', Rule::in(MarketingAssetValidationStatus::values())],
            'platform' => ['nullable', 'string', 'max:40'],
            'tag' => ['nullable', 'string', 'max:60'],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($validated): JsonResponse {
            $query = MarketingAsset::query()->orderByDesc('created_at')->orderByDesc('id');

            foreach (['kind', 'campaign_id', 'content_idea_id', 'locale', 'product', 'sector', 'objective', 'validation_status'] as $field) {
                if (filled($validated[$field] ?? null)) {
                    $query->where($field, $validated[$field]);
                }
            }

            // Recherche « retrouver une ancienne vidéo via “publicité restaurant” » :
            // le nom, la description et les tags (jsonb) sont interrogeables.
            if (filled($validated['search'] ?? null)) {
                $search = trim((string) $validated['search']);
                $pattern = $this->likePattern($search);
                $query->where(function ($inner) use ($pattern): void {
                    $inner->where('name', 'like', $pattern)
                        ->orWhere('description', 'like', $pattern)
                        ->orWhereRaw('tags::text ILIKE ?', [$pattern]);
                });
            }

            if (filled($validated['platform'] ?? null)) {
                $platform = (string) $validated['platform'];
                $query->whereRaw('platforms::text ILIKE ?', [$this->likePattern($platform)]);
            }

            if (filled($validated['tag'] ?? null)) {
                $tag = (string) $validated['tag'];
                $query->whereRaw('tags::text ILIKE ?', [$this->likePattern($tag)]);
            }

            if (filled($validated['from'] ?? null)) {
                $query->where('created_at', '>=', Carbon::parse($validated['from'])->startOfDay());
            }
            if (filled($validated['to'] ?? null)) {
                $query->where('created_at', '<=', Carbon::parse($validated['to'])->endOfDay());
            }

            $page = $query->paginate((int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE));

            return new JsonResponse([
                'data' => $page->getCollection()->map(fn (MarketingAsset $a): array => $this->presentAsset($a))->all(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ]);
        });
    }

    /**
     * POST /platform/companies/{company}/marketing-os/assets
     *
     * Multipart : `file` optionnel (types texte) et obligatoire sinon.
     * Le binaire va sur le disk privé ; la base ne stocke que `disk` + `path`.
     */
    public function storeAsset(StoreMarketingAssetRequest $request, string $company): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);
        $payload = $request->validated();
        $file = $request->file('file');
        $kind = MarketingAssetKind::from((string) $payload['kind']);

        // Contrôle de cohérence AVANT toute écriture : un type binaire sans
        // fichier est refusé explicitement (pas de ressource fantôme).
        if ($kind->requiresFile() && $file === null) {
            return new JsonResponse([
                'message' => __('marketing.os.file_required', ['kind' => $kind->label()]),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->tenantManager->withinTenant($target, function () use ($payload, $file, $kind, $target): JsonResponse {
            $this->assertCampaignBelongsToTenant($payload['campaign_id'] ?? null);
            $this->assertIdeaBelongsToTenant($payload['content_idea_id'] ?? null);

            $body = $payload['body'] ?? null;
            unset($payload['body'], $payload['file']);

            $asset = new MarketingAsset;
            $asset->fill($payload);
            $asset->company_id = (string) $target->id;
            $asset->version = $this->nextVersionFor((string) $target->id, (string) $payload['name']);
            $asset->author_id = null; // Acteur plateforme : pas d'employé tenant.
            $asset->save();

            // Contenu texte (script, prompt, légende) : une ressource SANS
            // fichier n'a rien à stocker sur disque, mais son texte ne doit pas
            // être perdu — il est conservé sur la ligne (aucun binaire en base).
            if ($body !== null && $body !== '') {
                $asset->description = $asset->description ?? $body;
                $asset->save();
            }

            if ($file !== null) {
                $stored = $this->storage->store($asset, $kind, $file);
                $asset->disk = $stored['disk'];
                $asset->path = $stored['path'];
                $asset->mime_type = $stored['mime_type'];
                $asset->extension = $stored['extension'];
                $asset->size = $stored['size'];
                $asset->save();
            }

            // Journalisation de l'ajout à la médiathèque (brief §J) : on trace
            // la VERSION créée, pour que « qui a ajouté quoi » reste vérifiable.
            $this->audit->assetCreated($asset, [
                'campaign_id' => $asset->campaign_id,
                'content_idea_id' => $asset->content_idea_id,
            ]);

            return new JsonResponse([
                'data' => $this->presentAsset($asset->refresh()),
                'meta' => [
                    // Une ressource texte (script, prompt, légende) vit sans
                    // binaire : on l'expose pour que l'UI n'attende pas de média.
                    'text_only' => $asset->hasFile() === false,
                    'stored_on_disk' => $asset->hasFile(),
                ],
            ], Response::HTTP_CREATED);
        });
    }

    /**
     * GET /platform/companies/{company}/marketing-os/assets/{asset}/publications
     *
     * Section G du brief — « cette vidéo a-t-elle déjà été publiée ? sur
     * quelles plateformes ? à quelle date ? avec quelle description ? quel
     * compte ? quel lien ? quels résultats ? peut-on la réutiliser ? ».
     * La ressource d'origine reste distincte de ses publications.
     */
    public function assetPublications(string $company, int $asset): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($asset): JsonResponse {
            $model = MarketingAsset::query()->findOrFail($asset);

            $publications = MarketingPublication::query()
                ->where('marketing_asset_id', $model->id)
                ->orderByDesc('created_at')
                ->get();

            return new JsonResponse([
                'data' => [
                    'asset' => $this->presentAsset($model),
                    'publications' => $publications->map(fn (MarketingPublication $p): array => $this->presentPublication($p))->all(),
                    'published_count' => $publications->filter(fn (MarketingPublication $p): bool => $p->isPublished())->count(),
                    'platforms' => $publications->pluck('platform')->unique()->values()->all(),
                    // « Peut-on la réutiliser ? » ne peut pas être une constante :
                    // la réponse dépend de l'état RÉEL de la ressource (droits
                    // expirés, validation rejetée). Une ressource sans droits
                    // déclarés reste réutilisable — on ne bloque pas sur une
                    // information absente, on la signale.
                    'reusable' => $model->isReusable(),
                    'reuse_blockers' => $model->reuseBlockers(),
                ],
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // E / F — Publications
    // ─────────────────────────────────────────────────────────────────────

    /** GET /platform/companies/{company}/marketing-os/publications */
    public function indexPublications(Request $request, string $company): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', Rule::in(MarketingPublicationStatus::values())],
            'mode' => ['nullable', 'string', Rule::in(MarketingPublicationMode::values())],
            'content_idea_id' => ['nullable', 'integer', 'min:1'],
            'marketing_asset_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($validated, $target): JsonResponse {
            $query = MarketingPublication::query()->orderByDesc('created_at')->orderByDesc('id');

            foreach (['platform', 'status', 'mode', 'content_idea_id', 'marketing_asset_id'] as $field) {
                if (filled($validated[$field] ?? null)) {
                    $query->where($field, $validated[$field]);
                }
            }

            if (filled($validated['from'] ?? null)) {
                $query->where('created_at', '>=', Carbon::parse($validated['from'])->startOfDay());
            }
            if (filled($validated['to'] ?? null)) {
                $query->where('created_at', '<=', Carbon::parse($validated['to'])->endOfDay());
            }

            $page = $query->paginate((int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE));

            return new JsonResponse([
                'data' => $page->getCollection()->map(fn (MarketingPublication $p): array => $this->presentPublication($p))->all(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    // Statuts distincts exposés pour les onglets de l'écran.
                    'status_counts' => $this->publicationStatusCounts(),
                    'direct_available' => $this->modes->isDirectAvailable((string) $target->id),
                ],
            ]);
        });
    }

    /** POST /platform/companies/{company}/marketing-os/publications */
    public function storePublication(StoreMarketingPublicationRequest $request, string $company): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);
        $payload = $request->validated();

        return $this->tenantManager->withinTenant($target, function () use ($payload, $target): JsonResponse {
            $this->assertIdeaBelongsToTenant($payload['content_idea_id'] ?? null);
            $this->assertAssetBelongsToTenant($payload['marketing_asset_id'] ?? null);

            $companyId = (string) $target->id;
            $directAvailable = $this->modes->isDirectAvailable($companyId);

            // Le mode est CALCULÉ : le client ne peut pas déclarer « direct »
            // si aucun connecteur n'est réellement configuré.
            $requested = $payload['mode'] ?? null;
            $mode = ($requested === MarketingPublicationMode::Direct->value && $directAvailable)
                ? MarketingPublicationMode::Direct
                : MarketingPublicationMode::Assisted;

            $publication = new MarketingPublication;
            $publication->fill($payload);
            $publication->company_id = $companyId;
            $publication->mode = $mode;
            $publication->created_by = null; // Acteur plateforme.

            if (($payload['scheduled_at'] ?? null) !== null && ($payload['status'] ?? null) === null) {
                $publication->status = MarketingPublicationStatus::Scheduled;
            }

            $publication->save();

            return new JsonResponse([
                'data' => $this->presentPublication($publication->refresh()),
                'meta' => [
                    'direct_available' => $directAvailable,
                    'mode_explanation' => $directAvailable
                        ? __('marketing.os.mode_direct_explanation')
                        : __('marketing.os.mode_assisted_explanation'),
                ],
            ], Response::HTTP_CREATED);
        });
    }

    /**
     * PATCH /platform/companies/{company}/marketing-os/publications/{publication}/status
     *
     * Seul chemin autorisé pour atteindre `published`. `confirm_published`
     * exige l'URL réelle : ouvrir une page de réseau social ne suffit jamais.
     */
    public function updatePublicationStatus(UpdateMarketingPublicationStatusRequest $request, string $company, int $publication): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);
        $validated = $request->validated();

        return $this->tenantManager->withinTenant($target, function () use ($validated, $publication): JsonResponse {
            $model = MarketingPublication::query()->findOrFail($publication);
            $action = (string) $validated['action'];
            $previousStatus = $model->status->value;

            // ── Idempotence ───────────────────────────────────────────────
            // Rejouer la MÊME transition (double clic, retry réseau, job
            // re-livré) ne doit ni échouer ni fabriquer un événement qui n'a
            // pas eu lieu. On répond 200 avec le même état et `idempotent:
            // true`, sans écrire en base ni dans le journal d'audit.
            if ($this->isIdempotentReplay($model, $action, $validated)) {
                return new JsonResponse([
                    'data' => $this->presentPublication($model),
                    'meta' => [
                        'mode' => $model->mode->value,
                        'idempotent' => true,
                        'changed' => false,
                    ],
                ]);
            }

            switch ($action) {
                case 'confirm_published':
                    $this->modes->markPublished(
                        $model,
                        (string) $validated['published_url'],
                        $validated['platform_account'] ?? null,
                        isset($validated['published_at']) ? Carbon::parse($validated['published_at']) : null,
                        $validated['stats'] ?? null,
                        $validated['external_post_id'] ?? null,
                    );
                    break;

                case 'failed':
                    $this->modes->markFailed($model, (string) $validated['error_message']);
                    break;

                case 'scheduled':
                    $model->status = MarketingPublicationStatus::Scheduled;
                    $model->scheduled_at = Carbon::parse((string) $validated['scheduled_at']);
                    $model->save();
                    break;

                default:
                    $model->status = MarketingPublicationStatus::from($action);
                    $model->save();
                    break;
            }

            $model->refresh();

            // Journalisation : uniquement sur un changement RÉEL (brief §J).
            $this->audit->publicationTransition(
                $model,
                $this->auditActionFor($action),
                $previousStatus,
                $validated['error_message'] ?? null,
                ['action' => $action],
            );

            return new JsonResponse([
                'data' => $this->presentPublication($model),
                'meta' => [
                    'mode' => $model->mode->value,
                    'idempotent' => false,
                    'changed' => true,
                    'previous_status' => $previousStatus,
                ],
            ]);
        });
    }

    /**
     * L'action demandée est-elle déjà l'état courant ?
     *
     * Les trois cas d'idempotence couverts correspondent aux trois actions
     * du contrat qui portent une DONNÉE en plus du statut : une confirmation
     * de publication (URL), une planification (date) et un échec (motif).
     * Deux valeurs identiques = rejeu ; deux valeurs différentes = vraie
     * demande, traitée par le chemin normal.
     *
     * @param  array<string, mixed>  $validated
     */
    private function isIdempotentReplay(MarketingPublication $model, string $action, array $validated): bool
    {
        $current = $model->status;

        return match ($action) {
            'confirm_published' => $current === MarketingPublicationStatus::Published
                && $model->published_url === ($validated['published_url'] ?? null),

            'failed' => $current === MarketingPublicationStatus::Failed
                && $model->error_message === ($validated['error_message'] ?? null),

            'scheduled' => $current === MarketingPublicationStatus::Scheduled
                && $model->scheduled_at !== null
                && $model->scheduled_at->equalTo(Carbon::parse((string) $validated['scheduled_at'])),

            default => $current->value === $action,
        };
    }

    /** Nom d'action d'audit correspondant à la transition demandée. */
    private function auditActionFor(string $action): string
    {
        return match ($action) {
            'confirm_published' => 'marketing.publication.published',
            'failed' => 'marketing.publication.failed',
            default => 'marketing.publication.status_changed',
        };
    }

    /**
     * POST /platform/companies/{company}/marketing-os/publications/{publication}/stats/refresh
     *
     * Interroge l'API de la plateforme et enregistre les indicateurs
     * REELLEMENT renvoyes (#8530). Si la plateforme ne fournit pas la donnee
     * (pas d'API, pas de connecteur, pas d'identifiant externe, erreur), la
     * publication est marquee `stats_source = unavailable` avec un motif
     * explicite : aucune valeur n'est inventee, et une mesure anterieure
     * n'est jamais detruite.
     */
    public function refreshPublicationStats(string $company, int $publication): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($publication): JsonResponse {
            $model = MarketingPublication::query()->findOrFail($publication);
            $refreshed = $this->stats->refresh($model);

            return new JsonResponse([
                'data' => $this->presentPublication($refreshed->refresh()),
                'meta' => [
                    'measured' => $refreshed->hasMeasuredStats(),
                    'stats_source' => $refreshed->stats_source,
                    'stats_error' => $refreshed->stats_error,
                ],
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // H — Visibilite SEO / SEA / AEO / GEO (#8531)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /platform/companies/{company}/marketing-os/visibility-objectives
     *
     * Objectifs de visibilite rattaches aux contenus. Chaque ligne expose sa
     * provenance d'indicateur (`measured` / `estimated` / `unavailable`) :
     * l'interface ne peut donc pas presenter une estimation comme une mesure.
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

            // Pagination reelle : un `limit` silencieux tronquait la liste a
            // 100 lignes sans le dire, et l'interface ne pouvait ni compter ni
            // atteindre les objectifs suivants.
            $page = $query->paginate((int) ($request->query('per_page') ?? self::DEFAULT_PER_PAGE));

            return new JsonResponse([
                'data' => $page->getCollection()->map(fn (MarketingVisibilityObjective $o): array => $this->presentVisibilityObjective($o))->all(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'channels' => MarketingVisibilityChannel::values(),
                    'metrics_sources' => MarketingVisibilityMetricsSource::values(),
                    // Rappel explicite : aucune visibilite n'est garantie.
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

            // Un indicateur sans provenance declaree n'est PAS presente comme
            // une mesure : on retombe sur `unavailable` (fail-closed).
            if (($payload['metrics'] ?? null) !== null && ($payload['metrics_source'] ?? null) === null) {
                $objective->metrics_source = MarketingVisibilityMetricsSource::Unavailable;
            }

            $objective->save();

            return new JsonResponse([
                'data' => $this->presentVisibilityObjective($objective->refresh()),
            ], Response::HTTP_CREATED);
        });
    }

    /**
     * GET /platform/companies/{company}/marketing-os/publishing-capabilities
     *
     * Matrice des plateformes et de leurs capacites REELLES (#8532) : quelles
     * plateformes autorisent une publication directe, et pour les autres, le
     * motif pour lequel le mode assiste s'applique. L'interface peut ainsi
     * expliquer l'etat au lieu de promettre une automatisation inexistante.
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

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, int>
     */
    private function publicationStatusCounts(): array
    {
        /** @var array<string, int> $counts */
        $counts = MarketingPublication::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        return $counts;
    }

    /**
     * Motif `LIKE`/`ILIKE` sur, pour une recherche utilisateur.
     *
     * Sans echappement, un `%` ou un `_` saisi dans le champ de recherche est
     * interprete comme un JOKER : chercher « 100% » ramenait tout le catalogue,
     * et « a_b » ramenait « axb ». Le depot applique deja cette regle partout
     * ailleurs (`addcslashes($v, '%_\\')`) : la mediatheque ne peut pas etre la
     * seule surface ou la recherche se comporte differemment.
     */
    private function likePattern(string $value): string
    {
        return '%'.addcslashes(trim($value), '%_\\').'%';
    }

    private function nextVersionFor(string $companyId, string $name): int
    {
        $max = MarketingAsset::query()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->max('version');

        return $max === null ? 1 : ((int) $max) + 1;
    }

    /** Empêche de rattacher une idée/ressource à une campagne d'un autre tenant. */
    private function assertCampaignBelongsToTenant(?int $campaignId): void
    {
        if ($campaignId === null) {
            return;
        }

        $exists = MarketingCampaign::query()->whereKey($campaignId)->exists();
        abort_unless($exists, 422, __('marketing.os.campaign_not_found'));
    }

    private function assertIdeaBelongsToTenant(?int $ideaId): void
    {
        if ($ideaId === null) {
            return;
        }

        abort_unless(ContentIdea::query()->whereKey($ideaId)->exists(), 422, __('marketing.os.idea_not_found'));
    }

    private function assertAssetBelongsToTenant(?int $assetId): void
    {
        if ($assetId === null) {
            return;
        }

        abort_unless(MarketingAsset::query()->whereKey($assetId)->exists(), 422, __('marketing.os.asset_not_found'));
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCampaign(MarketingCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'objective' => $campaign->objective->value,
            'audience' => $campaign->audience,
            'sector' => $campaign->sector,
            'product' => $campaign->product,
            'key_message' => $campaign->key_message,
            'channel' => $campaign->channel,
            'locale' => $campaign->locale,
            'starts_at' => $campaign->starts_at?->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
            'owner_id' => $campaign->owner_id,
            'budget_amount' => $campaign->budget_amount,
            'budget_currency' => $campaign->budget_currency,
            'status' => $campaign->status->value,
            'created_at' => $campaign->created_at?->toIso8601String(),
            'updated_at' => $campaign->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentIdea(ContentIdea $idea): array
    {
        return [
            'id' => $idea->id,
            'campaign_id' => $idea->campaign_id,
            'title' => $idea->title,
            'description' => $idea->description,
            'objective' => $idea->objective,
            'format' => $idea->format->value,
            'channel' => $idea->channel,
            'locale' => $idea->locale,
            'audience' => $idea->audience,
            'product' => $idea->product,
            'sector' => $idea->sector,
            'key_message' => $idea->key_message,
            'production_due_at' => $idea->production_due_at?->toIso8601String(),
            'publish_due_at' => $idea->publish_due_at?->toIso8601String(),
            'owner_id' => $idea->owner_id,
            'budget_amount' => $idea->budget_amount,
            'budget_currency' => $idea->budget_currency,
            'status' => $idea->status->value,
            'next_action' => $idea->next_action,
            'brief' => $idea->brief,
            'production_task_id' => $idea->production_task_id,
            'is_production_late' => $idea->isProductionLate(),
            'created_at' => $idea->created_at?->toIso8601String(),
            'updated_at' => $idea->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAsset(MarketingAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'campaign_id' => $asset->campaign_id,
            'content_idea_id' => $asset->content_idea_id,
            'name' => $asset->name,
            'description' => $asset->description,
            'kind' => $asset->kind->value,
            'mime_type' => $asset->mime_type,
            'extension' => $asset->extension,
            'size' => $asset->size,
            // Jamais d'URL publique fabriquée ici : le binaire vit hors webroot.
            'has_file' => $asset->hasFile(),
            'locale' => $asset->locale,
            'product' => $asset->product,
            'objective' => $asset->objective,
            'sector' => $asset->sector,
            'platforms' => $asset->platforms,
            'tags' => $asset->tags,
            'version' => $asset->version,
            'validation_status' => $asset->validation_status->value,
            'author_id' => $asset->author_id,
            'rights' => $asset->rights,
            'rights_expires_at' => $asset->rights_expires_at?->toIso8601String(),
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentVisibilityObjective(MarketingVisibilityObjective $objective): array
    {
        return [
            'id' => $objective->id,
            'channel' => $objective->channel->value,
            'channel_label' => $objective->channel->label(),
            'keyword' => $objective->keyword,
            'search_intent' => $objective->search_intent,
            'destination_url' => $objective->destination_url,
            'ad_campaign_ref' => $objective->ad_campaign_ref,
            'locale' => $objective->locale,
            'sector' => $objective->sector,
            'campaign_id' => $objective->campaign_id,
            'content_idea_id' => $objective->content_idea_id,
            'marketing_asset_id' => $objective->marketing_asset_id,
            'marketing_publication_id' => $objective->marketing_publication_id,
            'questions_answered' => $objective->questions_answered,
            'metrics' => $objective->metrics,
            'metrics_source' => $objective->metrics_source->value,
            'metrics_source_label' => $objective->metrics_source->label(),
            'metrics_note' => $objective->metrics_note,
            // Un indicateur est soit MESURE, soit declare estimation ou
            // indisponible : jamais presente comme un fait sans provenance.
            'has_measured_metrics' => $objective->hasMeasuredMetrics(),
            'has_linked_content' => $objective->hasLinkedContent(),
            'notes' => $objective->notes,
            'created_at' => $objective->created_at?->toIso8601String(),
            'updated_at' => $objective->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPublication(MarketingPublication $publication): array
    {
        return [
            'id' => $publication->id,
            'content_idea_id' => $publication->content_idea_id,
            'marketing_asset_id' => $publication->marketing_asset_id,
            'platform' => $publication->platform,
            'mode' => $publication->mode->value,
            'status' => $publication->status->value,
            'title' => $publication->title,
            'body' => $publication->body,
            'hashtags' => $publication->hashtags,
            'call_to_action' => $publication->call_to_action,
            'destination_url' => $publication->destination_url,
            'scheduled_at' => $publication->scheduled_at?->toIso8601String(),
            'published_at' => $publication->published_at?->toIso8601String(),
            'published_url' => $publication->published_url,
            'platform_account' => $publication->platform_account,
            'stats' => $publication->stats,
            'stats_source' => $publication->stats_source,
            'external_post_id' => $publication->external_post_id,
            'stats_fetched_at' => $publication->stats_fetched_at?->toIso8601String(),
            'stats_error' => $publication->stats_error,
            // Un indicateur est soit MESURE, soit declare indisponible :
            // l'interface ne doit jamais afficher un chiffre invente.
            'has_measured_stats' => $publication->hasMeasuredStats(),
            'error_message' => $publication->error_message,
            'attempts' => $publication->attempts,
            'attempt_history' => $publication->attempt_history,
            'is_published' => $publication->isPublished(),
            'created_at' => $publication->created_at?->toIso8601String(),
            'updated_at' => $publication->updated_at?->toIso8601String(),
        ];
    }
}
