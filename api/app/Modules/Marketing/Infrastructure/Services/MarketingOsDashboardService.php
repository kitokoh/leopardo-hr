<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services;

use App\Modules\Marketing\Domain\Enums\ContentIdeaStatus;
use App\Modules\Marketing\Domain\Enums\MarketingAssetValidationStatus;
use App\Modules\Marketing\Domain\Enums\MarketingCampaignStatus;
use App\Modules\Marketing\Domain\Enums\MarketingPublicationStatus;
use App\Modules\Marketing\Domain\Models\ContentIdea;
use App\Modules\Marketing\Domain\Models\MarketingAsset;
use App\Modules\Marketing\Domain\Models\MarketingCampaign;
use App\Modules\Marketing\Domain\Models\MarketingPublication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Marketing OS — agrégats du tableau de bord (brief §A).
 *
 * Chaque indicateur provient d'une DONNÉE RÉELLE de la base du tenant. Les
 * seules valeurs explicitement étiquetées comme non mesurées sont les
 * statistiques de publication : tant qu'aucune plateforme ne les a fournies,
 * `stats_source` vaut `unavailable` et l'interface doit l'afficher comme tel
 * (aucune estimation présentée comme un fait — brief §H).
 */
class MarketingOsDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(string $companyId): array
    {
        $now = Carbon::now();

        $ideasByStatus = $this->countByStatus(
            ContentIdea::query()->crossTenantForPlatformAdmin()->where('company_id', $companyId),
        );

        return [
            'generated_at' => $now->toIso8601String(),
            'content_pipeline' => [
                'to_imagine' => $this->sum($ideasByStatus, [ContentIdeaStatus::Idea->value]),
                'to_produce' => $this->sum($ideasByStatus, [ContentIdeaStatus::Briefing->value, ContentIdeaStatus::InProduction->value]),
                'to_validate' => $this->sum($ideasByStatus, [ContentIdeaStatus::InReview->value]),
                'to_publish' => $this->sum($ideasByStatus, [ContentIdeaStatus::Approved->value, ContentIdeaStatus::Scheduled->value]),
                'published' => $this->sum($ideasByStatus, [ContentIdeaStatus::Published->value]),
                'by_status' => $ideasByStatus,
            ],
            'late_content' => $this->lateContent($companyId, $now),
            'scheduled_publications' => $this->scheduledPublications($companyId, $now),
            'recent_publications' => $this->recentPublications($companyId),
            'active_campaigns' => $this->activeCampaigns($companyId),
            'unexploited_ideas' => $this->unexploitedIdeas($companyId),
            'recent_assets' => $this->recentAssets($companyId),
            'publication_performance' => $this->publicationPerformance($companyId),
            'next_actions' => $this->nextActions($companyId, $now),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<ContentIdea>  $query
     * @return array<string, int>
     */
    private function countByStatus(\Illuminate\Database\Eloquent\Builder $query): array
    {
        /** @var array<string, int> $counts */
        $counts = $query
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @param  list<string>  $statuses
     */
    private function sum(array $counts, array $statuses): int
    {
        $total = 0;
        foreach ($statuses as $status) {
            $total += $counts[$status] ?? 0;
        }

        return $total;
    }

    /**
     * Contenus en retard : date de publication dépassée, contenu encore ouvert.
     *
     * @return array<int, array<string, mixed>>
     */
    private function lateContent(string $companyId, Carbon $now): array
    {
        return ContentIdea::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $companyId)
            ->whereNotNull('publish_due_at')
            ->where('publish_due_at', '<', $now)
            ->whereNotIn('status', [ContentIdeaStatus::Published->value, ContentIdeaStatus::Cancelled->value])
            ->orderBy('publish_due_at')
            ->limit(20)
            ->get()
            ->map(static fn (ContentIdea $idea): array => [
                'id' => $idea->id,
                'title' => $idea->title,
                'status' => $idea->status instanceof ContentIdeaStatus ? $idea->status->value : (string) $idea->status,
                'publish_due_at' => $idea->publish_due_at?->toIso8601String(),
                'days_late' => $idea->publish_due_at !== null ? (int) $idea->publish_due_at->diffInDays($now) : null,
            ])
            ->all();
    }

    /**
     * Publications planifiées à venir.
     *
     * @return array<int, array<string, mixed>>
     */
    private function scheduledPublications(string $companyId, Carbon $now): array
    {
        return MarketingPublication::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $companyId)
            ->where('status', MarketingPublicationStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at')
            ->limit(20)
            ->get()
            ->map(static fn (MarketingPublication $publication): array => [
                'id' => $publication->id,
                'platform' => $publication->platform,
                'mode' => $publication->mode instanceof \App\Modules\Marketing\Domain\Enums\MarketingPublicationMode
                    ? $publication->mode->value
                    : (string) $publication->mode,
                'title' => $publication->title,
                'scheduled_at' => $publication->scheduled_at?->toIso8601String(),
                'is_overdue' => $publication->scheduled_at !== null && $publication->scheduled_at->isPast(),
            ])
            ->all();
    }

    /**
     * Dernières publications réellement mises en ligne, avec leur lien.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentPublications(string $companyId): array
    {
        return MarketingPublication::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $companyId)
            ->where('status', MarketingPublicationStatus::Published->value)
            ->orderByDesc('published_at')
            ->limit(10)
            ->get()
            ->map(static fn (MarketingPublication $publication): array => [
                'id' => $publication->id,
                'platform' => $publication->platform,
                'title' => $publication->title,
                'published_at' => $publication->published_at?->toIso8601String(),
                'published_url' => $publication->published_url,
                'mode' => $publication->mode instanceof \App\Modules\Marketing\Domain\Enums\MarketingPublicationMode
                    ? $publication->mode->value
                    : (string) $publication->mode,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeCampaigns(string $companyId): array
    {
        return MarketingCampaign::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $companyId)
            ->where('status', MarketingCampaignStatus::Active->value)
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get()
            ->map(static fn (MarketingCampaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'objective' => $campaign->objective instanceof \App\Modules\Marketing\Domain\Enums\MarketingObjective
                    ? $campaign->objective->value
                    : (string) $campaign->objective,
                'sector' => $campaign->sector,
                'starts_at' => $campaign->starts_at?->toIso8601String(),
                'ends_at' => $campaign->ends_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Idées non encore exploitées : ni ressource produite, ni publication.
     *
     * @return array<int, array<string, mixed>>
     */
    private function unexploitedIdeas(string $companyId): array
    {
        return ContentIdea::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $companyId)
            ->where('status', ContentIdeaStatus::Idea->value)
            ->whereNotExists(static function ($query) use ($companyId): void {
                $query->select(DB::raw('1'))
                    ->from('marketing_assets')
                    ->whereColumn('marketing_assets.content_idea_id', 'content_ideas.id')
                    ->where('marketing_assets.company_id', $companyId);
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(static fn (ContentIdea $idea): array => [
                'id' => $idea->id,
                'title' => $idea->title,
                'format' => $idea->format instanceof \App\Modules\Marketing\Domain\Enums\MarketingContentFormat
                    ? $idea->format->value
                    : (string) $idea->format,
                'created_at' => $idea->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentAssets(string $companyId): array
    {
        return MarketingAsset::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(static fn (MarketingAsset $asset): array => [
                'id' => $asset->id,
                'name' => $asset->name,
                'kind' => $asset->kind instanceof \App\Modules\Marketing\Domain\Enums\MarketingAssetKind
                    ? $asset->kind->value
                    : (string) $asset->kind,
                'validation_status' => $asset->validation_status instanceof MarketingAssetValidationStatus
                    ? $asset->validation_status->value
                    : (string) $asset->validation_status,
                'created_at' => $asset->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Performances constatées — et provenance explicite de la donnée.
     *
     * @return array<string, mixed>
     */
    private function publicationPerformance(string $companyId): array
    {
        $withStats = MarketingPublication::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $companyId)
            ->whereNotNull('stats')
            ->count();

        $published = MarketingPublication::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $companyId)
            ->where('status', MarketingPublicationStatus::Published->value)
            ->count();

        return [
            'published_total' => $published,
            'with_measured_stats' => $withStats,
            // Provenance : sans remontée de la plateforme, la valeur est
            // explicitement déclarée indisponible plutôt qu'estimée.
            'stats_source' => $withStats > 0 ? 'platform' : 'unavailable',
            'note' => $withStats > 0
                ? 'Statistiques fournies par la plateforme pour '.$withStats.' publication(s).'
                : 'Aucune statistique de plateforme disponible : aucune estimation n\'est affichée.',
        ];
    }

    /**
     * Prochaines actions prioritaires, dérivées d'un état réel.
     *
     * @return array<int, string>
     */
    private function nextActions(string $companyId, Carbon $now): array
    {
        $actions = [];

        $pendingAssets = MarketingAsset::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $companyId)
            ->where('validation_status', MarketingAssetValidationStatus::Pending->value)
            ->count();

        if ($pendingAssets > 0) {
            $actions[] = $pendingAssets.' ressource(s) en attente de validation.';
        }

        $late = ContentIdea::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $companyId)
            ->whereNotNull('publish_due_at')
            ->where('publish_due_at', '<', $now)
            ->whereNotIn('status', [ContentIdeaStatus::Published->value, ContentIdeaStatus::Cancelled->value])
            ->count();

        if ($late > 0) {
            $actions[] = $late.' contenu(s) en retard de publication.';
        }

        $overdue = MarketingPublication::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $companyId)
            ->where('status', MarketingPublicationStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $now)
            ->count();

        if ($overdue > 0) {
            $actions[] = $overdue.' publication(s) planifiée(s) dont la date est dépassée.';
        }

        $failed = MarketingPublication::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $companyId)
            ->where('status', MarketingPublicationStatus::Failed->value)
            ->count();

        if ($failed > 0) {
            $actions[] = $failed.' publication(s) en échec à traiter.';
        }

        if ($actions === []) {
            $actions[] = 'Aucune action prioritaire détectée sur les données actuelles.';
        }

        return $actions;
    }
}
