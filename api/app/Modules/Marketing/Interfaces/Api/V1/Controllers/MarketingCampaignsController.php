<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Marketing\Application\Actions\PromoteContentIdeaToProduction;
use App\Modules\Marketing\Domain\Enums\ContentIdeaStatus;
use App\Modules\Marketing\Domain\Enums\MarketingCampaignStatus;
use App\Modules\Marketing\Domain\Enums\MarketingContentFormat;
use App\Modules\Marketing\Domain\Enums\MarketingObjective;
use App\Modules\Marketing\Domain\Models\ContentIdea;
use App\Modules\Marketing\Domain\Models\MarketingCampaign;
use App\Modules\Marketing\Infrastructure\Services\MarketingOsAudit;
use App\Modules\Marketing\Infrastructure\Services\MarketingOsPresenter;
use App\Modules\Marketing\Interfaces\Api\V1\Controllers\Concerns\ResolvesMarketingTenant;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreContentIdeaRequest;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreMarketingCampaignRequest;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marketing OS — ressource STRATÉGIE (campagnes et idées).
 *
 * Extrait de `PlatformMarketingOsController` (1 022 lignes, quatre ressources
 * mêlées). Le découpage suit la règle du dépôt : un contrôleur porte un
 * agrégat HTTP, pas quatre. Les garanties transverses (pagination bornée,
 * échappement `LIKE`, gardes de rattachement tenant) vivent dans
 * `ResolvesMarketingTenant`.
 *
 * Toutes les requêtes s'exécutent DANS le contexte du tenant ciblé
 * (`TenantManager::withinTenant()`), donc le scope `BelongsToCompany` ajoute
 * le filtre `company_id` : l'isolation est tenue par le framework, pas par un
 * `where('company_id', …)` recopié à la main.
 */
final class MarketingCampaignsController extends Controller
{
    use ResolvesMarketingTenant;

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly PromoteContentIdeaToProduction $promote,
        private readonly MarketingOsAudit $audit,
        private readonly MarketingOsPresenter $presenter,
    ) {}

    /** GET /platform/companies/{company}/marketing-os/campaigns */
    public function indexCampaigns(Request $request, string $company): JsonResponse
    {
        // Les filtres de statut et d'objectif sont contraints par les
        // énumérations du domaine : une valeur hors vocabulaire est refusée
        // (422) au lieu de produire silencieusement une liste vide, que
        // l'interface afficherait comme « aucun résultat ».
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
                'data' => $page->getCollection()->map(fn (MarketingCampaign $c): array => $this->presenter->presentCampaign($c))->all(),
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

            return new JsonResponse(['data' => $this->presenter->presentCampaign($campaign->refresh())], Response::HTTP_CREATED);
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
                'data' => $page->getCollection()->map(fn (ContentIdea $i): array => $this->presenter->presentIdea($i))->all(),
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

            return new JsonResponse(['data' => $this->presenter->presentIdea($idea->refresh())], Response::HTTP_CREATED);
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

            return new JsonResponse(['data' => $this->presenter->presentIdea($model->refresh())]);
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
                'data' => $this->presenter->presentIdea($promoted->refresh()),
                'meta' => [
                    // Un échec de création de tâche n'est pas silencieux : il est
                    // exposé, et l'idée reste promue côté marketing.
                    'production_task_created' => $promoted->production_task_id !== null,
                    'idempotent' => $wasLinked,
                ],
            ]);
        });
    }
}
