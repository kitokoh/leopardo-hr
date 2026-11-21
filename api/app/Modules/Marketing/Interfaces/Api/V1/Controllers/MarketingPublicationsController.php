<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Marketing\Domain\Enums\MarketingPublicationMode;
use App\Modules\Marketing\Domain\Enums\MarketingPublicationStatus;
use App\Modules\Marketing\Domain\Models\MarketingPublication;
use App\Modules\Marketing\Infrastructure\Services\MarketingOsAudit;
use App\Modules\Marketing\Infrastructure\Services\MarketingOsPresenter;
use App\Modules\Marketing\Infrastructure\Services\MarketingPublicationModeResolver;
use App\Modules\Marketing\Infrastructure\Services\MarketingPublicationStatsService;
use App\Modules\Marketing\Interfaces\Api\V1\Controllers\Concerns\ResolvesMarketingTenant;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreMarketingPublicationRequest;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\UpdateMarketingPublicationStatusRequest;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marketing OS — ressource PUBLICATIONS (cycle de vie et statistiques).
 *
 * Extrait de `PlatformMarketingOsController` (1 022 lignes). Deux garanties
 * fonctionnelles portées ici :
 *
 *  - **direct vs assisté est toujours explicite** : `mode` est CALCULÉ, jamais
 *    deviné par le client, et `direct_available` est renvoyé pour l'expliquer ;
 *  - **aucune publication n'est marquée publiée sans preuve** : la
 *    confirmation exige `published_url` (règle de validation structurelle).
 */
final class MarketingPublicationsController extends Controller
{
    use ResolvesMarketingTenant;

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly MarketingPublicationModeResolver $modes,
        private readonly MarketingPublicationStatsService $stats,
        private readonly MarketingOsAudit $audit,
        private readonly MarketingOsPresenter $presenter,
    ) {}

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
                'data' => $page->getCollection()->map(fn (MarketingPublication $p): array => $this->presenter->presentPublication($p))->all(),
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
                'data' => $this->presenter->presentPublication($publication->refresh()),
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

            // ── Idempotence ──────────────────────────────────────────────
            // Rejouer la MÊME transition (double clic, retry réseau, job
            // re-livré) ne doit ni échouer ni fabriquer un événement qui n'a
            // pas eu lieu. On répond 200 avec le même état et `idempotent:
            // true`, sans écrire en base ni dans le journal d'audit.
            if ($this->isIdempotentReplay($model, $action, $validated)) {
                return new JsonResponse([
                    'data' => $this->presenter->presentPublication($model),
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
                'data' => $this->presenter->presentPublication($model),
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
     * POST /platform/companies/{company}/marketing-os/publications/{publication}/stats/refresh
     *
     * Interroge l'API de la plateforme et enregistre les indicateurs
     * REELLEMENT renvoyés (#8530). Si la plateforme ne fournit pas la donnée
     * (pas d'API, pas de connecteur, pas d'identifiant externe, erreur), la
     * publication est marquée `stats_source = unavailable` avec un motif
     * explicite : aucune valeur n'est inventée, et une mesure antérieure
     * n'est jamais détruite.
     */
    public function refreshPublicationStats(string $company, int $publication): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);

        return $this->tenantManager->withinTenant($target, function () use ($publication): JsonResponse {
            $model = MarketingPublication::query()->findOrFail($publication);
            $refreshed = $this->stats->refresh($model);

            return new JsonResponse([
                'data' => $this->presenter->presentPublication($refreshed->refresh()),
                'meta' => [
                    'measured' => $refreshed->hasMeasuredStats(),
                    'stats_source' => $refreshed->stats_source,
                    'stats_error' => $refreshed->stats_error,
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
}
