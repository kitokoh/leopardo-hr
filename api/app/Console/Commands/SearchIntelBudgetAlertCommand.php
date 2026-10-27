<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\SearchIntelligence\Domain\Models\Campaign;
use App\Notifications\SlackAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * GSI-12 (#8523) point 5 — alerte Slack sur dépassement de budget.
 *
 * Trois décisions qui font la différence entre une alerte utile et une alerte
 * qu'on finit par ignorer :
 *
 * 1. **Une alerte par campagne et par PERIODE, pas par exécution.** Sans cette
 *    clé de cache, la commande tournerait toutes les heures et re-notifierait
 *    la même campagne 24 fois par jour — le canal serait muet au bout de deux
 *    jours. La clé inclut la période (jour pour un budget quotidien, mois pour
 *    un budget global) : un dépassement qui dure reste signalé une fois par
 *    période, et un nouveau dépassement le mois suivant repart.
 *
 * 2. **On alerte sur un DÉPASSEMENT, jamais sur une prévision.** Un budget à
 *    1 000 € dépensé à 800 € n'est pas un incident : c'est une campagne qui
 *    marche. Seul `dépensé > budget` déclenche.
 *
 * 3. **Sans webhook configuré, la commande ne fait rien et sort en succès.**
 *    Une exception `Notification::route()` non configurée enverrait un job en
 *    échec dans la file toutes les heures. Absence de Slack = absence
 *    d'alerte, pas une panne du scheduler.
 *
 * La lecture est un agrégat SQL sur `campaign_metrics` : aucun chargement de
 * collection en mémoire, une campagne peut avoir 400 lignes quotidiennes.
 */
final class SearchIntelBudgetAlertCommand extends Command
{
    protected $signature = 'search-intel:budget-alerts
                            {--dry-run : afficher les dépassements sans envoyer d\'alerte}';

    protected $description = 'Alerte Slack quand une campagne dépasse son budget quotidien ou global (GSI-12)';

    /** Durée de silence pour une même campagne et une même période. */
    private const QUIET_SECONDS = 86400;

    public function handle(): int
    {
        $webhook = config('services.slack.monitoring_webhook');

        if (! is_string($webhook) || $webhook === '') {
            $this->info('Aucun webhook Slack configuré (services.slack.monitoring_webhook) — rien à envoyer.');

            return self::SUCCESS;
        }

        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $campaigns = Campaign::query()
            ->whereNotNull('daily_budget_cents')
            ->orWhere(function ($q): void {
                $q->whereNotNull('total_budget_cents');
            })
            ->get();

        if ($campaigns->isEmpty()) {
            $this->info('Aucune campagne budgétée — rien à vérifier.');

            return self::SUCCESS;
        }

        $alerted = 0;

        foreach ($campaigns as $campaign) {
            // Dépense du jour et du mois en deux agrégats, pas deux collections.
            $spentToday = (int) DB::table('campaign_metrics')
                ->where('campaign_id', $campaign->id)
                ->where('measured_on', '>=', $today->toDateString())
                ->sum('spend_cents');

            $spentThisMonth = (int) DB::table('campaign_metrics')
                ->where('campaign_id', $campaign->id)
                ->where('measured_on', '>=', $monthStart->toDateString())
                ->sum('spend_cents');

            foreach ($this->breaches($campaign, $spentToday, $spentThisMonth) as [$scope, $spent, $budget]) {
                $period = $scope === 'jour' ? $today->toDateString() : $monthStart->format('Y-m');
                $key = sprintf('search-intel:budget-alert:%d:%s:%s', $campaign->id, $scope, $period);

                if (Cache::has($key)) {
                    continue;
                }

                $this->line(sprintf(
                    '  %-12s campagne #%d — dépensé %s / budget %s (période %s)',
                    $scope,
                    $campaign->id,
                    $this->money($spent),
                    $this->money($budget),
                    $period,
                ));

                if (! $this->option('dry-run')) {
                    Notification::route('slack', $webhook)->notify(new SlackAlertNotification(
                        message: sprintf('Budget %s dépassé — campagne #%d', $scope, $campaign->id),
                        severity: 'warning',
                        context: [
                            'campagne' => $campaign->name ?? ('#'.$campaign->id),
                            'portee' => $scope,
                            'periode' => $period,
                            'depense_cents' => $spent,
                            'budget_cents' => $budget,
                            'depassement_cents' => $spent - $budget,
                        ],
                    ));

                    Cache::put($key, true, self::QUIET_SECONDS);
                }

                $alerted++;
            }
        }

        $this->info(sprintf('%d dépassement(s) détecté(s)%s.', $alerted, $this->option('dry-run') ? ' (dry-run, aucune alerte envoyée)' : ''));

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: int, 2: int}> paires [portée, dépensé, budget]
     */
    private function breaches(Campaign $campaign, int $spentToday, int $spentThisMonth): array
    {
        $out = [];

        $daily = $campaign->daily_budget_cents;
        if (is_int($daily) && $daily > 0 && $spentToday > $daily) {
            $out[] = ['jour', $spentToday, $daily];
        }

        $total = $campaign->total_budget_cents;
        if (is_int($total) && $total > 0 && $spentThisMonth > $total) {
            $out[] = ['global', $spentThisMonth, $total];
        }

        return $out;
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }
}
