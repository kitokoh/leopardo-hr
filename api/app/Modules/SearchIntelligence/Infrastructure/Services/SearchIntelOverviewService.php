<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Services;

use App\Modules\SearchIntelligence\Domain\Contracts\LeadMetricsProviderInterface;
use App\Modules\SearchIntelligence\Domain\Enums\AeoQuestionStatus;
use App\Modules\SearchIntelligence\Domain\Enums\CampaignStatus;
use App\Modules\SearchIntelligence\Domain\Enums\ContentStatus;
use App\Modules\SearchIntelligence\Domain\Enums\IntegrationStatus;
use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueStatus;
use App\Modules\SearchIntelligence\Domain\Models\AeoQuestion;
use App\Modules\SearchIntelligence\Domain\Models\Campaign;
use App\Modules\SearchIntelligence\Domain\Models\CampaignMetric;
use App\Modules\SearchIntelligence\Domain\Models\ContentItem;
use App\Modules\SearchIntelligence\Domain\Models\GeoQuery;
use App\Modules\SearchIntelligence\Domain\Models\Integration;
use App\Modules\SearchIntelligence\Domain\Models\Keyword;
use App\Modules\SearchIntelligence\Domain\Models\SeoIssue;
use App\Modules\SearchIntelligence\Domain\Models\SeoPage;
use Illuminate\Support\Carbon;

/**
 * GSI-9 (#8520) — vue d'ensemble « Growth & Search Intelligence ».
 *
 * Trois principes non négociables, hérités de l'audit produit :
 *
 *  1. **Tous les compteurs sont RÉELS.** Chaque chiffre est un `count()` ou un
 *     `sum()` sur les tables du module, exécuté au moment de la requête. Aucune
 *     valeur codée en dur, aucun échantillon de démonstration.
 *  2. **Un état absent est affiché comme absent.** Quand une intégration n'est
 *     pas connectée, la réponse porte `connected: false` et le front affiche
 *     « non connecté » ; quand aucune source de leads n'est branchée, `leads`
 *     vaut `null`, pas `0`.
 *  3. **Chaque KPI porte ses propres métadonnées** — `source`, `period`,
 *     `updated_at`, `reliability`. Un écran de pilotage doit pouvoir dire d'où
 *     vient un chiffre et à quel point on peut s'y fier ; sans ça, un compteur
 *     est une affirmation sans preuve.
 *
 * Les métriques de leads passent par `LeadMetricsProviderInterface` (contrat,
 * pas import du BC-11 GROWTH — règle d'isolation #7959).
 */
final class SearchIntelOverviewService
{
    /**
     * Fiabilité par nature de source. Ce n'est pas une note de qualité : c'est
     * ce qu'on peut honnêtement affirmer à côté du chiffre.
     *
     *  - `measured` : compteur direct sur nos propres tables, exact par
     *    construction ;
     *  - `declared` : saisi ou validé par un humain, exact sur ce qui a été
     *    saisi mais dépend de la discipline de saisie ;
     *  - `unavailable` : aucune source branchée, la valeur est `null`.
     */
    private const RELIABILITY_MEASURED = 'measured';

    private const RELIABILITY_DECLARED = 'declared';

    private const RELIABILITY_UNAVAILABLE = 'unavailable';

    public function __construct(
        private readonly LeadMetricsProviderInterface $leads,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(int $leadWindowDays = 30): array
    {
        return [
            'generated_at' => Carbon::now()->toIso8601String(),
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
        return [
            'pages_tracked' => $this->kpi(
                SeoPage::query()->count(),
                'seo_pages.count',
                'all_time',
                self::RELIABILITY_MEASURED,
            ),
            'pages_healthy' => $this->kpi(
                SeoPage::query()->healthy()->count(),
                'seo_pages.healthy',
                'current',
                self::RELIABILITY_MEASURED,
            ),
            'open_issues' => $this->kpi(
                SeoIssue::query()->actionable()->count(),
                'seo_issues.actionable',
                'current',
                self::RELIABILITY_MEASURED,
            ),
            // « Vérifiées » = correction confirmée par un re-scan. C'est la
            // seule mesure honnête du travail réellement soldé : une issue
            // « appliquée » ne prouve rien tant que le scan ne l'a pas revue
            // disparue.
            'verified_issues' => $this->kpi(
                SeoIssue::query()->where('status', SeoIssueStatus::Verified->value)->count(),
                'seo_issues.verified',
                'all_time',
                self::RELIABILITY_MEASURED,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function seaSection(): array
    {
        $monthStart = Carbon::now()->startOfMonth();

        // Dépense du mois en cours : somme des relevés SAISIS. S'il n'y a
        // aucun relevé, la valeur est `null` et non `0` — « aucune dépense
        // enregistrée » et « zéro euro dépensé » ne sont pas la même chose.
        $monthSpend = CampaignMetric::query()
            ->whereDate('measured_on', '>=', $monthStart->toDateString())
            ->sum('spend_cents');

        $hasMetrics = CampaignMetric::query()
            ->whereDate('measured_on', '>=', $monthStart->toDateString())
            ->exists();

        return [
            'campaigns_total' => $this->kpi(
                Campaign::query()->count(),
                'campaigns.count',
                'all_time',
                self::RELIABILITY_MEASURED,
            ),
            'campaigns_running' => $this->kpi(
                Campaign::query()->where('status', CampaignStatus::Active->value)->count(),
                'campaigns.active',
                'current',
                self::RELIABILITY_MEASURED,
            ),
            'spend_month_cents' => $this->kpi(
                $hasMetrics ? (int) $monthSpend : null,
                'campaign_metrics.spend',
                $monthStart->toDateString().'..'.Carbon::now()->toDateString(),
                $hasMetrics ? self::RELIABILITY_DECLARED : self::RELIABILITY_UNAVAILABLE,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function aeoSection(): array
    {
        return [
            'questions_total' => $this->kpi(
                AeoQuestion::query()->count(),
                'aeo_questions.count',
                'all_time',
                self::RELIABILITY_MEASURED,
            ),
            'questions_published' => $this->kpi(
                AeoQuestion::query()->where('status', AeoQuestionStatus::Published->value)->count(),
                'aeo_questions.published',
                'current',
                self::RELIABILITY_MEASURED,
            ),
            'questions_awaiting_review' => $this->kpi(
                AeoQuestion::query()
                    ->whereIn('status', [
                        AeoQuestionStatus::Draft->value,
                        AeoQuestionStatus::InReview->value,
                        AeoQuestionStatus::Approved->value,
                    ])
                    ->count(),
                'aeo_questions.awaiting_review',
                'current',
                self::RELIABILITY_MEASURED,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function geoSection(): array
    {
        return [
            'queries_active' => $this->kpi(
                GeoQuery::query()->active()->count(),
                'geo_queries.active',
                'current',
                self::RELIABILITY_MEASURED,
            ),
        ];
    }

    /**
     * Contenus par statut — la répartition complète, pas seulement le total :
     * « 40 contenus » ne dit rien, « 12 publiés / 25 en brouillon » dit où en
     * est le pipeline.
     *
     * @return array<string, mixed>
     */
    private function contentSection(): array
    {
        $byStatus = [];

        foreach (ContentStatus::cases() as $status) {
            $byStatus[$status->value] = $this->kpi(
                ContentItem::query()->where('status', $status->value)->count(),
                'content_items.status',
                'current',
                self::RELIABILITY_MEASURED,
            );
        }

        return [
            'items_total' => $this->kpi(
                ContentItem::query()->count(),
                'content_items.count',
                'all_time',
                self::RELIABILITY_MEASURED,
            ),
            'by_status' => $byStatus,
        ];
    }

    /** @return array<string, mixed> */
    private function keywordSection(): array
    {
        return [
            'keywords_total' => $this->kpi(
                Keyword::query()->count(),
                'keywords.count',
                'all_time',
                self::RELIABILITY_MEASURED,
            ),
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
        $available = $this->leads->name() !== 'null';
        $metrics = $this->leads->leadMetrics($days);

        return [
            'window_days' => $days,
            'period' => Carbon::now()->subDays($days)->toDateString().'..'.Carbon::now()->toDateString(),
            'source' => $this->leads->name(),
            'available' => $available,
            'reliability' => $available ? self::RELIABILITY_MEASURED : self::RELIABILITY_UNAVAILABLE,
            // `leadMetrics()` du provider nul renvoie `null` ; on le laisse tel
            // quel. Un `0` fabriqué ici serait exactement le mensonge que cette
            // vue existe pour éviter.
            'metrics' => $metrics,
            'leads_total' => $this->kpi(
                // Le contrat `LeadMetricsProviderInterface` expose `total`
                // (nombre de leads de la fenetre), pas `leads` : lire une cle
                // inexistante rendait ce KPI structurellement toujours `null`
                // (#8520).
                is_array($metrics) ? $metrics['total'] : null,
                'marketing_leads',
                $days.'d',
                $available ? self::RELIABILITY_MEASURED : self::RELIABILITY_UNAVAILABLE,
            ),
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
                // Aucune donnée n'est exposée pour un connecteur non connecté :
                // il n'y a rien à lire, et prétendre le contraire produirait un
                // « 0 » que le lecteur interpréterait comme une mesure.
                'reliability' => $integration?->status === IntegrationStatus::Connected
                    ? self::RELIABILITY_MEASURED
                    : self::RELIABILITY_UNAVAILABLE,
            ];
        }

        return $rows;
    }

    /**
     * Enveloppe commune d'un KPI : la valeur et tout ce qu'il faut pour la
     * juger. `updated_at` est l'instant de calcul — ces compteurs sont lus en
     * direct, il n'y a pas de cache à rafraîchir.
     *
     * Forme retenue : **objet tracé** `{value, source, period, updated_at,
     * reliability}`. Les métadonnées sont dans la valeur du KPI et non dans une
     * table parallèle : un client qui lit `seo.pages_tracked` ne peut pas
     * obtenir le chiffre tout en ignorant d'où il vient, ce qui est précisément
     * le mode de défaillance que GSI-9 existe pour fermer.
     *
     * @return array<string, mixed>
     */
    private function kpi(int|float|null $value, string $source, string $period, string $reliability): array
    {
        return [
            'value' => $value,
            'source' => $source,
            'period' => $period,
            'updated_at' => Carbon::now()->toIso8601String(),
            'reliability' => $reliability,
        ];
    }
}
