<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Platform\Infrastructure\Services\SchedulerRunLedger;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * #7967 — rattrapage des tâches planifiées perdues pendant le spin-down Render.
 *
 * LE PROBLÈME
 * Le plan free Render endort le conteneur web après ~15 min d'inactivité, et
 * le scheduler tourne dedans (`WEB_SCHEDULER_LOOP`). `schedule:run` ne
 * rattrape rien : une échéance due pendant le sommeil n'existe plus pour
 * Laravel. Avant ce correctif, une nuit de spin-down effaçait purement et
 * simplement l'acquisition de congés, les relances de recouvrement et le
 * rapprochement de paiements — sans trace, sans alerte.
 *
 * LE PRINCIPE
 * Le scheduler enregistre chaque passage dans `scheduler_runs` (une ligne par
 * commande et par échéance théorique). Cette commande compare les échéances
 * THÉORIQUES (déduites de l'expression cron) à ce registre : tout créneau dû
 * et absent a été perdu. Les créneaux perdus sont alors soit rejoués (si la
 * commande est déclarée rattrapable), soit signalés (sinon).
 *
 * POURQUOI TOUT N'EST PAS REJOUÉ
 * Rejouer `billing:generate-invoices` trois jours plus tard émet des factures
 * rétroactives avec une date de prestation fausse : c'est un incident
 * comptable, pas une réparation. Le registre de `SchedulerRunLedger` liste
 * explicitement ce qui est sûr à rejouer (outbox, expirations, relances
 * dédoublonnées) et ce qui ne l'est jamais. Par défaut : on ne rejoue pas, on
 * alerte.
 *
 * UTILISATION
 *   scheduler:catch-up                  # diagnostic + rattrapage des éligibles
 *   scheduler:catch-up --dry-run        # diagnostic seul, aucune exécution
 *   scheduler:catch-up --report         # diagnostic seul, code retour 0
 *
 * Code retour : 0 = rien de perdu (ou rien d'irréparable) ; 1 = des créneaux
 * ont été perdus sur des commandes non rattrapables (action humaine requise).
 */
class SchedulerCatchUpCommand extends Command
{
    protected $signature = 'scheduler:catch-up
        {--dry-run : Rapporte les créneaux perdus sans rien rejouer}
        {--report : Rapporte seulement (alias de --dry-run, code retour 0)}
        {--max-lookback=10080 : Fenêtre maximale d\'analyse en minutes (défaut 7 jours)}
        {--prune=90 : Purge du registre au-delà de N jours (0 = pas de purge)}';

    protected $description = 'Rattrape les tâches planifiées perdues pendant un spin-down (issue #7967)';

    public function __construct(
        private readonly SchedulerRunLedger $ledger,
    ) {
        parent::__construct();
    }

    public function handle(Schedule $schedule): int
    {
        $dryRun = (bool) $this->option('dry-run') || (bool) $this->option('report');
        $reportOnly = (bool) $this->option('report');
        $maxLookback = max(60, (int) $this->option('max-lookback'));

        $now = Carbon::now();

        // Table absente (déploiement antérieur à la migration, ou rollback) :
        // on sort proprement. Sans cette garde, la tâche planifiée échouerait à
        // chaque passage avec une QueryException illisible — et c'est
        // exactement le moment où on ne veut pas ajouter du bruit.
        if (! $this->ledger->tableExists()) {
            $this->warn('scheduler:catch-up — table `scheduler_runs` absente (migration non appliquée) : rien à analyser.');

            return self::SUCCESS;
        }

        // On balaie toutes les entrées de planning réellement enregistrées —
        // pas une liste recopiée à la main, qui dériverait du planning.
        $events = $this->replayableEvents($schedule);

        $replayed = 0;
        $unrecoverable = [];
        $recoverable = [];

        foreach ($events as $event) {
            $command = $this->ledger->commandFor($event);

            // Entrée jamais observée (déploiement récent, ou commande ajoutée
            // après le dernier passage) : son absence du registre ne prouve
            // rien. On ne conclut pas — sans quoi le premier run après mise en
            // service déclarerait perdu tout l'historique de la fenêtre.
            if ($this->ledger->firstObservedSlot($this->ledger->keyFor($event)) === null) {
                continue;
            }

            // Fenêtre : min(fenêtre de la commande, borne globale). Une commande
            // autorisée à 24 h ne doit pas faire analyser 7 jours.
            $lookback = $this->ledger->catchUpLookbackMinutes($command);
            $windowMinutes = $lookback === null
                ? $maxLookback
                : min($maxLookback, $lookback);

            $from = $now->copy()->subMinutes($windowMinutes);
            $missed = $this->ledger->missedSlots($event, $from, $now);

            if ($missed === []) {
                continue;
            }

            if (! $this->ledger->isCatchUpAllowed($command)) {
                // Perdu et non rattrapable : c'est CE bloc qui doit remonter,
                // avec la raison, pour qu'un humain décide.
                $unrecoverable[$command] = [
                    'slots' => $missed,
                    'reason' => $this->ledger->refusalReason($command)
                        ?? 'Non déclarée rattrapable (idempotence non prouvée).',
                ];

                continue;
            }

            $recoverable[$command] = $missed;

            if ($dryRun) {
                continue;
            }

            foreach ($missed as $slot) {
                if ($this->replay($event, $slot, $command)) {
                    $replayed++;
                }
            }
        }

        $this->renderReport($recoverable, $unrecoverable, $replayed, $dryRun);

        if (! $dryRun && $this->option('prune') !== null) {
            $pruneDays = (int) $this->option('prune');

            if ($pruneDays > 0) {
                $deleted = $this->ledger->prune($pruneDays);

                if ($deleted > 0) {
                    $this->line("Registre purgé : {$deleted} ligne(s) de plus de {$pruneDays} jour(s).");
                }
            }
        }

        // --report est un mode diagnostic : il ne doit pas faire échouer une
        // sonde de supervision à cause d'un créneau perdu, seulement informer.
        if ($reportOnly) {
            return self::SUCCESS;
        }

        return $unrecoverable === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Rejoue un créneau. Retourne true si ce créneau a effectivement été
     * exécuté par cet appel (false s'il était déjà couvert — cas normal sur
     * un second conteneur ou après un redémarrage).
     */
    private function replay(Event $event, Carbon $slot, string $command): bool
    {
        // Réservation AVANT exécution : si deux conteneurs rattrapent en même
        // temps, le second perd la course sur la contrainte UNIQUE et
        // n'exécute rien. Réserver après coup laisserait la fenêtre ouverte
        // pour un double débit.
        if (! $this->ledger->record($event, $slot, 'catch_up')) {
            return false;
        }

        try {
            // On exécute via l'événement Laravel plutôt que par
            // `Artisan::call($command) : on hérite ainsi des arguments déjà
            // résolus (« billing:report --json »), du fuseau et du sandbox de
            // la définition de planning — exactement ce que schedule:run aurait
            // lancé, une heure plus tôt.
            $event->run(app());

            $this->line("  ↻ rejoué : {$command} (créneau {$slot->toIso8601String()})");

            return true;
        } catch (Throwable $e) {
            // Le créneau reste marqué comme traité : une commande qui échoue au
            // rejeu échouera aussi au suivant, et boucler indéfiniment sur la
            // même échéance saturerait le conteneur. On remonte l'échec.
            report($e);

            $this->error("  ✗ échec au rejeu : {$command} (créneau {$slot->toIso8601String()}) — {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Entrées de planning interrogeables. Les commandes en arrière-plan ou avec
     * closure ne sont pas rejouables de façon fiable : on les écarte du
     * rattrapage (elles restent planifiées normalement).
     *
     * @return list<Event>
     */
    private function replayableEvents(Schedule $schedule): array
    {
        $events = [];

        foreach ($schedule->events() as $event) {
            if ($event->command === null) {
                continue;
            }

            $events[] = $event;
        }

        return $events;
    }

    /**
     * @param  array<string, list<Carbon>>  $recoverable
     * @param  array<string, array{slots: list<Carbon>, reason: string}>  $unrecoverable
     */
    private function renderReport(array $recoverable, array $unrecoverable, int $replayed, bool $dryRun): void
    {
        if ($recoverable === [] && $unrecoverable === []) {
            $this->info('scheduler:catch-up — aucun créneau perdu détecté.');

            return;
        }

        if ($recoverable !== []) {
            $verb = $dryRun ? 'à rejouer' : 'rejoués';
            $this->warn('Créneaux perdus sur des commandes rattrapables ('.$verb.') :');

            foreach ($recoverable as $command => $slots) {
                $this->line(sprintf('  %-45s %d créneau(x) — %s', $command, count($slots), $this->ledger->catchUpReason($command) ?? ''));
            }
        }

        if ($unrecoverable !== []) {
            $this->error('Créneaux perdus sur des commandes NON rattrapables — action humaine requise :');

            foreach ($unrecoverable as $command => $info) {
                // `$info['slots']` est non vide par construction (on n'entre ici
                // qu'avec au moins un créneau manqué), mais on lit le DERNIER
                // élément sans `end()` : sur un tableau vide `end()` renvoie
                // `false` et `->toIso8601String()` lèverait une erreur fatale
                // au milieu du rapport — exactement quand l'opérateur a besoin
                // de le lire.
                $slots = $info['slots'];
                $last = $slots[count($slots) - 1];

                $this->line(sprintf('  %-45s %d créneau(x)', $command, count($slots)));
                $this->line('      dernier manqué : '.$last->toIso8601String());
                $this->line('      raison : '.$info['reason']);
            }
        }

        if (! $dryRun) {
            $this->info("Créneaux rejoués : {$replayed}.");
        }
    }
}
