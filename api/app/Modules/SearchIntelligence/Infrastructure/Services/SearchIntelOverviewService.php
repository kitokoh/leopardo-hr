<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Services;

use App\Modules\SearchIntelligence\Domain\Contracts\LeadMetricsProviderInterface;
use App\Modules\SearchIntelligence\Domain\Enums\AeoQuestionStatus;
use App\Modules\SearchIntelligence\Domain\Enums\IntegrationStatus;
use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueStatus;
use App\Modules\SearchIntelligence\Domain\Models\AeoQuestion;
use App\Modules\SearchIntelligence\Domain\Models\Campaign;
use App\Modules\SearchIntelligence\Domain\Models\ContentItem;
use App\Modules\SearchIntelligence\Domain\Models\GeoQuery;
use App\Modules\SearchIntelligence\Domain\Models\Integration;
use App\Modules\SearchIntelligence\Domain\Models\Keyword;
use App\Modules\SearchIntelligence\Domain\Models\SeoIssue;
use App\Modules\SearchIntelligence\Domain\Models\SeoPage;

/**
 * GSI-9 (#8520) — vue d'ensemble « Growth & Search Intelligence ».
 *
 * Deux principes non négociables, hérités de l'audit produit :
 *
 *  1. **Tous les compteurs sont RÉELS.** Chaque chiffre est un `count()` sur
 *     les tables du module, exécuté au moment de la requête. Aucune valeur
 *     codée en dur, aucun échantillon de démonstration.
 *  2. **Un état absent est affiché comme absent.** Quand une intégration n'est
 *     pas connectée, la réponse porte `connected: false` et le front affiche
 *     « non connecté » ; quand aucune source de leads n'est branchée, `leads`
 *     vaut `null`, pas `0`.
 *
 * Les métriques de leads passent par `LeadMetricsProviderInterface` (contrat,
 * pas import du BC-11 GROWTH — règle d'isolation #7959).
 */
final class SearchIntelOverviewService
{
    public function __construct(
        private readonly LeadMetricsProviderInterface $leads,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(int $leadWindowDays = 30): array
    {
        return [
            'seo' => $this->seoSection(),
            'sea' => $this->seaSection(),
            'aeo' => $this->aeoSection(),
            'geo' => $this->geoSection(),
            'content' => $this->contentSection(),
            'keywords' => $this->keywordSection(),
            'leads' => $this->leadsSection($leadWindowDays),
            'integrations' => $this->integrationsSection(),
        ];
    }

    /** @return array<string, mixed> */
    private function seoSection(): array
    {
        $actionable = SeoIssue::query()->actionable()->count();

        return [
            'pages_tracked' => SeoPage::query()->count(),
            'pages_healthy' => SeoPage::query()->healthy()->count(),
            'open_issues' => $actionable,
            'resolved_issues' => SeoIssue::query()
                ->where('status', SeoIssueStatus::Resolved->value)
                ->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function seaSection(): array
    {
        return [
            'campaigns_total' => Campaign::query()->count(),
            'campaigns_running' => Campaign::query()->running()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function aeoSection(): array
    {
        return [
            'questions_total' => AeoQuestion::query()->count(),
            'questions_published' => AeoQuestion::query()
                ->where('status', AeoQuestionStatus::Published->value)
                ->count(),
            'questions_awaiting_review' => AeoQuestion::query()
                ->whereIn('status', [
                    AeoQuestionStatus::Draft->value,
                    AeoQuestionStatus::Reviewed->value,
                ])
                ->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function geoSection(): array
    {
        return [
            'queries_active' => GeoQuery::query()->active()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function contentSection(): array
    {
        return [
            'items_total' => ContentItem::query()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function keywordSection(): array
    {
        return [
            'keywords_total' => Keyword::query()->count(),
        ];
    }

    /**
     * Leads et funnel. `null` (et non `0`) quand aucune source n'est branchée :
     * l'écran doit pouvoir distinguer « pas de données » de « aucun lead ».
     *
     * @return array<string, mixed>
     */
    private function leadsSection(int $days): array
    {
        return [
            'window_days' => $days,
            'source' => $this->leads->name(),
            'available' => $this->leads->name() !== 'null',
            'metrics' => $this->leads->leadMetrics($days),
        ];
    }

    /**
     * État réel de chaque connecteur. Une intégration jamais configurée est
     * absente de la table : on la déclare quand même `not_connected` pour que
     * l'écran montre les quatre lignes attendues plutôt qu'un vide ambigu.
     *
     * @return list<array<string, mixed>>
     */
    private function integrationsSection(): array
    {
        /** @var array<string, Integration> $byProvider */
        $byProvider = Integration::query()->get()->keyBy('provider')->all();

        $rows = [];

        foreach (['gsc', 'ga4', 'google_ads', 'ms_ads'] as $provider) {
            /** @var Integration|null $integration */
            $integration = $byProvider[$provider] ?? null;

            $rows[] = [
                'provider' => $provider,
                'status' => $integration?->status->value ?? IntegrationStatus::NotConnected->value,
                'connected' => $integration?->status === IntegrationStatus::Connected,
                'last_synced_at' => $integration?->last_synced_at?->toIso8601String(),
                'last_error' => $integration?->last_error,
            ];
        }

        return $rows;
    }
}
