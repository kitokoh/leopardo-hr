<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Marketing\Domain\Enums\MarketingAssetKind;
use App\Modules\Marketing\Domain\Enums\MarketingAssetValidationStatus;
use App\Modules\Marketing\Domain\Enums\MarketingObjective;
use App\Modules\Marketing\Domain\Models\MarketingAsset;
use App\Modules\Marketing\Domain\Models\MarketingPublication;
use App\Modules\Marketing\Infrastructure\Services\MarketingAssetStorage;
use App\Modules\Marketing\Infrastructure\Services\MarketingOsAudit;
use App\Modules\Marketing\Infrastructure\Services\MarketingOsPresenter;
use App\Modules\Marketing\Interfaces\Api\V1\Controllers\Concerns\ResolvesMarketingTenant;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreMarketingAssetRequest;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Marketing OS — ressource MÉDIATHÈQUE (ressources et leurs publications).
 *
 * Extrait de `PlatformMarketingOsController` (1 022 lignes). Le binaire va sur
 * le disk PRIVÉ ; la base ne stocke que `disk` + `path`. Une ressource peut
 * alimenter plusieurs publications : le lien est porté par
 * `marketing_publications.marketing_asset_id`.
 */
final class MarketingAssetsController extends Controller
{
    use ResolvesMarketingTenant;

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly MarketingAssetStorage $storage,
        private readonly MarketingOsAudit $audit,
        private readonly MarketingOsPresenter $presenter,
    ) {}

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
                'data' => $page->getCollection()->map(fn (MarketingAsset $a): array => $this->presenter->presentAsset($a))->all(),
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
                // #8675 — le nom d'origine etait calcule puis JETE : la base ne
                // gardait que le nom de stockage aleatoire, donc un telechargement
                // ne pouvait pas proposer un nom lisible. Il est desormais
                // persiste (colonne nullable, migration 2026_10_18_120000_8675).
                $asset->original_name = $stored['original_name'];
                $asset->save();
            }

            // Journalisation de l'ajout à la médiathèque (brief §J) : on trace
            // la VERSION créée, pour que « qui a ajouté quoi » reste vérifiable.
            $this->audit->assetCreated($asset, [
                'campaign_id' => $asset->campaign_id,
                'content_idea_id' => $asset->content_idea_id,
            ]);

            return new JsonResponse([
                'data' => $this->presenter->presentAsset($asset->refresh()),
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
     * GET /platform/companies/{company}/marketing-os/assets/{asset}
     *
     * Détail d'UNE ressource. Manquait complètement : la médiathèque ne
     * savait exposer qu'une LISTE, si bien qu'un écran de détail n'avait
     * aucun endpoint à appeler et devait retrouver la ressource dans la page
     * courante — impossible dès qu'elle sortait de la fenêtre de pagination.
     */
    public function showAsset(string $company, int $asset): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($asset): JsonResponse {
            $model = MarketingAsset::query()->findOrFail($asset);

            return new JsonResponse([
                'data' => $this->presenter->presentAsset($model),
                'meta' => [
                    'downloadable' => $model->hasFile(),
                    'text_only' => $model->hasFile() === false,
                ],
            ]);
        });
    }

    /**
     * GET /platform/companies/{company}/marketing-os/assets/{asset}/download
     *
     * Sert le binaire depuis le disk PRIVÉ (#8675).
     *
     * C'est le chaînon manquant de la médiathèque : `storeAsset()` écrivait
     * bien sur `local` (hors webroot, ce qui est le bon choix — aucun binaire
     * ne doit être servi par une URL publique devinable), mais AUCUNE route
     * ne le relisait. Autrement dit : on pouvait téléverser une vidéo et ne
     * plus jamais la revoir. Les briefs §C (« aperçu si possible ») et §F
     * (mode assisté : « sélectionner et ouvrir le média ») étaient donc
     * inaccessibles en pratique.
     *
     * Trois propriétés tenues ici :
     *
     *  - **isolation tenant par construction** : la ressource est résolue
     *    DANS le contexte du tenant ciblé (`withinTenant`), le scope
     *    `BelongsToCompany` du modèle ajoutant le filtre `company_id` — une
     *    ressource d'une autre entreprise est donc introuvable (404), jamais
     *    servie ;
     *  - **pas de fuite de chemin** : on ne renvoie jamais `path` (nom de
     *    stockage aléatoire) au client ; le nom proposé vient de
     *    `downloadName()`, dérivé du nom d'origine sanitisé ou du titre ;
     *  - **honnêteté sur l'état** : une ressource TEXTE (script, prompt,
     *    légende) n'a pas de binaire ; elle renvoie 404 avec un motif
     *    explicite plutôt qu'un fichier vide ou une erreur 500.
     */
    public function downloadAsset(string $company, int $asset): StreamedResponse|JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($asset): StreamedResponse|JsonResponse {
            $model = MarketingAsset::query()->findOrFail($asset);

            if (! $model->hasFile()) {
                return new JsonResponse([
                    'message' => __('marketing.os.asset_has_no_file'),
                ], Response::HTTP_NOT_FOUND);
            }

            $disk = Storage::disk((string) $model->disk);

            // Le binaire peut manquer alors que la ligne existe (purge de
            // stockage, restauration partielle) : c'est un 404 explicite, pas
            // une 500 qui laisserait croire à une panne du module.
            if (! $disk->exists((string) $model->path)) {
                return new JsonResponse([
                    'message' => __('marketing.os.asset_file_missing'),
                ], Response::HTTP_NOT_FOUND);
            }

            return $disk->download((string) $model->path, $model->downloadName());
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
                    'asset' => $this->presenter->presentAsset($model),
                    'publications' => $publications->map(fn (MarketingPublication $p): array => $this->presenter->presentPublication($p))->all(),
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

    private function nextVersionFor(string $companyId, string $name): int
    {
        $max = MarketingAsset::query()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->max('version');

        return $max === null ? 1 : ((int) $max) + 1;
    }
}
