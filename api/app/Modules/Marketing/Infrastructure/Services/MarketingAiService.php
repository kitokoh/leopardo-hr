<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services;

use App\AI\LLMClient;
use App\Modules\Marketing\Domain\Models\PostPublication;
use App\Modules\Marketing\Domain\Models\SocialPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * IA marketing — Issue #7753.
 *
 * Deux usages, branchés sur l'infra IA transverse (`App\AI\LLMClient`,
 * drivers fake/groq/openai/claude — import autorisé par la garde
 * d'isolation #5584, `App\AI` n'est pas un module) :
 *
 *   - `suggestPost()` : brief + plateformes + ton + langue → proposition de
 *     texte de post. L'humain reste dans la boucle : la suggestion est
 *     retournée au client, jamais publiée automatiquement ;
 *   - `suggestReply()` : commentaire reçu → proposition de réponse (#7754),
 *     même règle — validation humaine obligatoire ;
 *   - `weeklyReport()` : agrégats 7 jours glissants (posts, publications par
 *     plateforme, campagnes email si le CRM est migré) + synthèse. La
 *     synthèse IA est meilleure-effort : si le LLM échoue ou est désactivé,
 *     un résumé déterministe est produit — le bilan n'est JAMAIS bloquant.
 *
 * Module horizontal : aucun vocabulaire vertical (école, restaurant…) dans
 * les prompts — le contexte métier vient du brief de l'utilisateur.
 */
class MarketingAiService
{
    public function __construct(private readonly LLMClient $llm) {}

    /**
     * @param  list<string>  $platforms
     */
    public function suggestPost(string $brief, array $platforms, string $tone, string $locale): string
    {
        $platformList = $platforms === [] ? 'réseaux sociaux génériques' : implode(', ', $platforms);

        $system = 'Tu es un expert en marketing digital pour PME. '
            .'Tu rédiges des posts prêts à publier, adaptés aux plateformes demandées '
            .'(longueur, hashtags, emojis avec parcimonie). '
            .'Réponds UNIQUEMENT avec le texte du post, sans préambule ni commentaire. '
            .'Langue de rédaction : '.$locale.'.';

        $user = "Plateformes cibles : {$platformList}.\nTon souhaité : {$tone}.\nBrief : {$brief}";

        return $this->complete($system, $user);
    }

    public function suggestReply(string $comment, string $platform, string $tone, string $locale): string
    {
        $system = 'Tu es un community manager professionnel. '
            .'Tu rédiges une réponse courte, utile et courtoise au commentaire fourni, '
            .'adaptée à la plateforme indiquée. Ne promets rien que l\'entreprise n\'a pas dit. '
            .'Réponds UNIQUEMENT avec le texte de la réponse, sans préambule. '
            .'Langue : '.$locale.'.';

        $user = "Plateforme : {$platform}.\nTon souhaité : {$tone}.\nCommentaire reçu : {$comment}";

        return $this->complete($system, $user);
    }

    /**
     * @return array{
     *     period: array{from: string, to: string},
     *     stats: array<string, mixed>,
     *     summary: string,
     *     summary_source: string
     * }
     */
    public function weeklyReport(string $companyId): array
    {
        $to = now();
        $from = now()->subDays(7);

        $published = SocialPost::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', SocialPost::STATUS_PUBLISHED)
            ->whereBetween('published_at', [$from, $to])
            ->count();

        $failed = SocialPost::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', SocialPost::STATUS_FAILED)
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        $upcoming = SocialPost::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', SocialPost::STATUS_SCHEDULED)
            ->where('scheduled_at', '>', $to)
            ->count();

        /** @var array<string, int> $byPlatform */
        $byPlatform = PostPublication::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', PostPublication::STATUS_SUCCESS)
            ->whereBetween('published_at', [$from, $to])
            ->selectRaw('platform, count(*) as total')
            ->groupBy('platform')
            ->pluck('total', 'platform')
            ->all();

        $stats = [
            'posts_published' => $published,
            'posts_failed' => $failed,
            'posts_scheduled_upcoming' => $upcoming,
            'publications_by_platform' => $byPlatform,
        ];

        // Campagnes email (#7751) — meilleures-effort : uniquement si le CRM
        // est migré sur ce déploiement (aucun import inter-modules, #5584).
        if (Schema::hasTable('crm_campaigns') && Schema::hasTable('crm_campaign_sends')) {
            $campaignsStarted = DB::table('crm_campaigns')
                ->where('company_id', $companyId)
                ->whereBetween('started_at', [$from, $to])
                ->count();

            $emailsSent = DB::table('crm_campaign_sends')
                ->where('company_id', $companyId)
                ->where('status', 'sent')
                ->whereBetween('sent_at', [$from, $to])
                ->count();

            $stats['campaigns_started'] = $campaignsStarted;
            $stats['campaign_emails_sent'] = $emailsSent;
        }

        [$summary, $source] = $this->summarize($stats);

        return [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'stats' => $stats,
            'summary' => $summary,
            'summary_source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array{0: string, 1: string}
     */
    private function summarize(array $stats): array
    {
        $json = json_encode($stats, JSON_UNESCAPED_UNICODE);

        try {
            $summary = $this->complete(
                'Tu es un analyste marketing. À partir des statistiques JSON fournies '
                .'(activité marketing des 7 derniers jours d\'une entreprise), rédige un bilan '
                .'court en français : 3 à 5 phrases, factuel, avec une recommandation concrète. '
                .'Réponds UNIQUEMENT avec le bilan.',
                (string) $json,
            );

            return [$summary, 'ai'];
        } catch (Throwable $e) {
            Log::info('marketing.ai.weekly_report_fallback', ['error' => $e->getMessage()]);

            return [$this->deterministicSummary($stats), 'fallback'];
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function deterministicSummary(array $stats): string
    {
        $published = is_int($stats['posts_published'] ?? null) ? $stats['posts_published'] : 0;
        $failed = is_int($stats['posts_failed'] ?? null) ? $stats['posts_failed'] : 0;
        $upcoming = is_int($stats['posts_scheduled_upcoming'] ?? null) ? $stats['posts_scheduled_upcoming'] : 0;

        $summary = sprintf(
            'Sur les 7 derniers jours : %d publication(s) réussie(s), %d échec(s), %d post(s) planifié(s) à venir.',
            $published,
            $failed,
            $upcoming,
        );

        if (isset($stats['campaign_emails_sent']) && is_int($stats['campaign_emails_sent'])) {
            $summary .= sprintf(' Emails de campagne envoyés : %d.', $stats['campaign_emails_sent']);
        }

        return $summary;
    }

    private function complete(string $system, string $user): string
    {
        $response = $this->llm->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ]);

        if ($response->failed() || trim($response->content) === '') {
            throw new RuntimeException('IA marketing indisponible : '.($response->error ?? 'réponse vide.'));
        }

        return trim($response->content);
    }
}
