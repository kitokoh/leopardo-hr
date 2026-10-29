<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * #7967 — enregistre le passage du scheduler pour une entrée de planning.
 *
 * Branché sur `ScheduledTaskStarting`, le MÊME événement que
 * `ScheduledTaskRunRecorder` — donc un seul point d'accroche, au lieu d'un
 * `->before()` ajouté à chacune des 46 entrées de `routes/console.php` (où la
 * première définition oubliée rouvrirait le trou que ce correctif ferme).
 *
 * POURQUOI UNE TABLE DIFFÉRENTE DE `scheduled_task_runs`
 * `scheduled_task_runs` (PA2-QA-006) garde LA DERNIÈRE exécution par tâche,
 * écrasée à chaque run : par construction, elle ne peut pas dire qu'une
 * échéance a été sautée. Le rattrapage a besoin de l'INVERSE — un historique
 * line-by-line des échéances, contraint par `UNIQUE (key, due_at)`, qui sert à
 * la fois de preuve de perte et de verrou anti-double-exécution.
 *
 * POURQUOI `Starting` ET PAS `Finished`
 * `schedule:run` ne réessaie pas une commande qui a échoué à 09:00 : il passe
 * au créneau suivant. En enregistrant AVANT l'exécution, le registre conserve
 * cette sémantique — le créneau a été COUVERT, qu'il ait réussi ou non. Le
 * rattrapage reste donc réservé aux créneaux réellement perdus (spin-down) et
 * ne devient pas un mécanisme de retry silencieux qui réexécuterait
 * indéfiniment une commande cassée.
 *
 * ROBUSTESSE
 * Un registre de diagnostic ne doit JAMAIS faire échouer la tâche métier qu'il
 * observe : toute erreur (table absente avant migration, base indisponible)
 * est avalée et seulement tracée.
 */
class RecordSchedulerRun
{
    public function __construct(
        private readonly SchedulerRunLedger $ledger,
    ) {}

    /**
     * Forme `Event::listen(X::class, [Class::class, 'method'])` : Laravel
     * résout la classe via le conteneur, donc l'injection par constructeur
     * fonctionne ici (même forme que `ScheduledTaskRunRecorder`).
     */
    public function onStarting(ScheduledTaskStarting $event): void
    {
        try {
            $this->ledger->record(
                $event->task,
                $this->ledger->dueSlotFor($event->task, Carbon::now()),
            );
        } catch (Throwable $e) {
            // Volontairement silencieux côté tâche métier (cf. docblock), mais
            // tracé : sans trace, un registre muet passerait pour un scheduler
            // parfaitement sain alors qu'aucun créneau n'est enregistré.
            report($e);
        }
    }
}
