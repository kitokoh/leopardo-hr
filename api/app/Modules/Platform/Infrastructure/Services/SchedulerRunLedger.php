<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7967 — registre des passages du scheduler + rattrapage des créneaux perdus.
 *
 * POURQUOI CE SERVICE EXISTE
 * Le plan free Render endort le conteneur web (spin-down ~15 min) et le
 * scheduler tourne DEDANS. Laravel ne rattrape jamais un `schedule:run` qui
 * n'a pas eu lieu : une tâche due pendant le sommeil est perdue, sans trace.
 * Les tâches concernées ne sont pas cosmétiques (acquisition de congés,
 * génération de factures, relances de recouvrement).
 *
 * CE QUE CE SERVICE NE FAIT PAS
 * Il ne rejoue pas « toutes les commandes en retard ». Rejouer aveuglément
 * un `audit:purge`, un `payroll:precalculate` ou un `travel:settle-sales` des
 * jours plus tard est plus dangereux que la perte elle-même. Seules les
 * commandes DÉCLARÉES idempotentes et rattrapables (registre ci-dessous) sont
 * éligibles ; tout le reste est signalé comme perdu et laissé à un humain.
 *
 * GARANTIES
 *  - Idempotence par la base : contrainte UNIQUE (key, due_at). Deux
 *    conteneurs, un redémarrage en boucle ou deux appels concurrents ne
 *    peuvent pas produire deux exécutions du même créneau.
 *  - Échéances alignées sur l'expression cron, pas sur l'heure réelle : un
 *    run à 09:07 pour un cron `0 9 * * *` enregistre le créneau 09:00.
 *  - Bornage : on ne remonte jamais au-delà de `maxLookbackMinutes`, sinon
 *    un conteneur resté éteint une semaine déclencherait une rafale de
 *    factures rétroactives le lundi matin.
 */
class SchedulerRunLedger
{
    private const TABLE = 'scheduler_slot_runs';

    /**
     * Commandes dont le rattrapage est SÛR, avec la fenêtre maximale de
     * rattrapage. Volontairement restrictif : une commande n'entre ici que si
     * la rejouer plus tard produit le même état qu'une exécution à l'heure.
     *
     * Hors registre = jamais rejouée automatiquement (signalée seulement).
     *
     * @var array<string, array{lookback:int, reason:string}>
     */
    private const CATCH_UP_ALLOWLIST = [
        // Idempotent par conception : le scheduler Laravel relance déjà
        // `schedule:run` chaque minute et la commande ne publie que ce qui est
        // dû (statut `scheduled` + `scheduled_at` dépassé). Rejouer plus tard
        // publie le même lot, une seule fois par ligne.
        'announcements:publish-scheduled' => [
            'lookback' => 24 * 60,
            'reason' => 'Publication d\'annonces dues — filtre sur scheduled_at, idempotent par ligne.',
        ],
        'marketing:publish-scheduled-posts' => [
            'lookback' => 24 * 60,
            'reason' => 'Même contrat que announcements:publish-scheduled (statut + date dus).',
        ],

        // Outbox : la consommation est un drain de messages en attente, pas une
        // action horodatée. Rejouer draine le backlog — exactement l'effet
        // voulu après un spin-down (et l'inverse d'une perte).
        'travel:outbox-dispatch' => [
            'lookback' => 6 * 60,
            'reason' => 'Drain d\'outbox — rejouer consomme le backlog accumulé.',
        ],
        'restaurant:outbox-dispatch' => [
            'lookback' => 6 * 60,
            'reason' => 'Drain d\'outbox Restaurant — même contrat.',
        ],
        'fuel:outbox-dispatch' => [
            'lookback' => 6 * 60,
            'reason' => 'Drain d\'outbox FuelStation — même contrat.',
        ],
        'travel:webhook-dispatch' => [
            'lookback' => 6 * 60,
            'reason' => 'Drain des livraisons webhook en attente (retry borné côté transport).',
        ],

        // Expirations : purement dérivées de l'horloge. Ne rien faire pendant
        // le spin-down laisse des lignes expirées visibles ; rejouer corrige
        // l'état sans effet de bord monétaire.
        'travel:expire-pending-bookings' => [
            'lookback' => 6 * 60,
            'reason' => 'Expiration dérivée de l\'horloge (statut → annulé).',
        ],
        'travel:expire-adverts' => [
            'lookback' => 24 * 60,
            'reason' => 'Expiration dérivée de l\'horloge (visibilité des annonces).',
        ],
        'hospitality:expire-pending-reservations' => [
            'lookback' => 6 * 60,
            'reason' => 'Expiration dérivée de l\'horloge (libère l\'inventaire).',
        ],

        // Relances et rappels : le pire cas d'un rejeu est une notification en
        // double, jamais une écriture comptable. Les tables de dédoublonnage
        // (crm_task_reminders, communication_follow_ups) l'empêchent déjà.
        'crm:tasks:send-overdue-reminders' => [
            'lookback' => 12 * 60,
            'reason' => 'Table crm_task_reminders UNIQUE(task_id, remind_date).',
        ],
        'communication:send-follow-ups' => [
            'lookback' => 12 * 60,
            'reason' => 'Table communication_follow_ups (une relance par échéance).',
        ],
        'onboarding:send-reminders' => [
            'lookback' => 48 * 60,
            'reason' => 'Fenêtre 20h–28h sur la date de création — un run tardif rattrape les mêmes sociétés.',
        ],

        // Surveillance : ne modifie aucune donnée métier, seulement des alertes.
        'edge:monitor' => [
            'lookback' => 6 * 60,
            'reason' => 'Sonde de nœuds silencieux — lecture seule.',
        ],
    ];

    /**
     * Commandes explicitement EXCLUES du rattrapage automatique, malgré un nom
     * qui pourrait faire croire le contraire. Documentées pour que la décision
     * ne soit pas reprise à l'envers lors d'une revue.
     */
    private const NEVER_CATCH_UP = [
        'billing:generate-invoices' => 'Génération monétaire rétroactive : une facture émise en retard est un incident comptable (TVA, date de prestation). Un humain tranche.',
        'billing:check-overdue' => 'Change le statut de recouvrement des clients : rejouer après coup peut basculer un client en impayé sur une relance manquée.',
        'billing:reconcile-payments' => 'Rapprochement bancaire : la fenêtre de réconciliation doit rester celle du fournisseur de paiement.',
        'payroll:precalculate' => 'Pré-calcul de paie : dépend de la date de paie courante, un rejeu tardif écrase un calcul plus récent.',
        'travel:settle-sales' => 'Règlement mensuel des ventes : irréversible côté transporteurs.',
        'leave:accrue' => 'Acquisition de congés : rejouer un mois manqué crédite des jours rétroactifs, décision RH explicite.',
        'leave:carry-forward' => 'Report annuel de congés : arbitrage RH annuel.',
        'audit:purge' => 'Purge RGPD : rejouer après coup peut purger une fenêtre de rétention plus récente que prévu.',
        'ai:purge-audit-logs' => 'Purge de rétention : même raison.',
        'biometric:purge-expired' => 'Purge de templates biométriques : même raison, données sensibles.',
        'tts:purge' => 'Purge de fichiers temporaires : aucun bénéfice à un rejeu, seulement un coût disque/CPU.',
        'growth:archive-clicks' => 'Archivage hebdomadaire : agrégation non rejouable sans double comptage.',
    ];

    /**
     * Enregistre un passage du scheduler pour l'entrée de planification donnée.
     *
     * @return bool true si le créneau a été enregistré, false s'il l'était déjà
     *              (donc si une exécution concurrente ou antérieure l'a couvert).
     */
    public function record(Event $event, Carbon $dueAt, string $trigger = 'scheduled'): bool
    {
        // `insertOrIgnore` et non `insert` + rattrapage de l'exception : sur
        // PostgreSQL, une violation de contrainte AVORTE la transaction en
        // cours (« current transaction is aborted »), et toutes les requêtes
        // suivantes de la même transaction échouent — y compris celles du
        // scheduler qu'on est censé ne pas déranger. `insertOrIgnore` compile
        // en `ON CONFLICT DO NOTHING`, qui ne lève rien.
        //
        // Conséquence pratique : le booléen de retour devient la SEULE source
        // de vérité sur « ce créneau était-il déjà couvert ? » — c'est lui qui
        // empêche deux conteneurs de rejouer la même échéance.
        $inserted = DB::table(self::TABLE)->insertOrIgnore([
            'key' => $this->keyFor($event),
            'command' => $this->commandFor($event),
            'expression' => $event->getExpression(),
            'due_at' => $dueAt,
            'trigger' => $trigger,
            'executed_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return $inserted > 0;
    }

    /**
     * Créneau théorique correspondant à un instant réel.
     *
     * Un run à 09:07 pour `0 9 * * *` appartient au créneau 09:00 : sans cet
     * alignement, chaque minute d'exécution créerait une clé différente et la
     * contrainte UNIQUE ne dédoublonnerait plus rien.
     */
    public function dueSlotFor(Event $event, Carbon $at): Carbon
    {
        $cron = new CronExpression($event->getExpression());
        $tz = $at->getTimezone();

        // getPreviousRunDate est STRICTEMENT antérieur : à 09:00:00 pile il
        // renvoie la veille à 09:00, pas le créneau courant. Or un run qui
        // tombe exactement sur son échéance appartient à CE créneau — d'où la
        // recherche décalée d'une minute vers l'avant, qui ramène 09:00 quand
        // l'instant vaut 09:00.
        $slot = $cron->getPreviousRunDate(
            $at->copy()->addMinute(),
            allowCurrentDate: false,
            timeZone: $this->cronTimeZone($event),
        );

        return Carbon::instance($slot)->setTimezone($tz)->startOfMinute();
    }

    /**
     * Fuseau attendu par `dragonmantank/cron-expression` : une CHAÎNE, jamais
     * un objet `DateTimeZone`. `Event::$timezone` accepte les deux ; passer
     * l'objet tel quel fonctionne à l'exécution mais casse le contrat de type.
     */
    private function cronTimeZone(Event $event): string
    {
        return $event->timezone instanceof \DateTimeZone
            ? $event->timezone->getName()
            : (string) $event->timezone;
    }

    /**
     * Créneaux dus entre `from` (exclu) et `to` (inclus) pour cette entrée.
     *
     * @return list<Carbon>
     */
    public function dueSlotsBetween(Event $event, Carbon $from, Carbon $to): array
    {
        $expression = $event->getExpression();

        if (! CronExpression::isValidExpression($expression)) {
            return [];
        }

        $cron = new CronExpression($expression);
        $slots = [];
        $cursor = $from->copy();

        // Garde-fou : un cron `* * * * *` sur une fenêtre de 7 jours produirait
        // 10 080 itérations. On borne la boucle et on s'arrête proprement.
        for ($i = 0; $i < 5_000; $i++) {
            $next = $cron->getNextRunDate($cursor, allowCurrentDate: false, timeZone: $this->cronTimeZone($event));
            $slot = Carbon::instance($next)->setTimezone($to->getTimezone())->startOfMinute();

            if ($slot->greaterThan($to)) {
                break;
            }

            $slots[] = $slot;
            $cursor = $slot;
        }

        return $slots;
    }

    /**
     * Créneaux dus mais absents du registre — donc perdus pendant un spin-down.
     *
     * POINT CRITIQUE — on ne peut pas conclure « perdu » à partir d'une absence
     * seule. Un registre vide ne distingue pas « le scheduler n'a jamais tourné »
     * de « le scheduler tourne mais le registre vient d'être créé ». Sans cette
     * distinction, le premier passage après le déploiement déclarerait perdues
     * TOUTES les échéances des 7 derniers jours — et rejouerait une semaine de
     * factures sur une base neuve.
     *
     * D'où la borne basse : on ne remonte jamais avant le premier créneau
     * réellement observé pour cette entrée. Avant cette date, le mécanisme
     * n'existait pas ; il n'y a rien à rattraper, seulement une période non
     * instrumentée.
     *
     * @return list<Carbon>
     */
    public function missedSlots(Event $event, Carbon $from, Carbon $to): array
    {
        $key = $this->keyFor($event);

        $firstObserved = $this->firstObservedSlot($key);

        if ($firstObserved === null) {
            // Jamais observée : aucune conclusion possible sur cette fenêtre.
            return [];
        }

        $lowerBound = $from->greaterThan($firstObserved) ? $from : $firstObserved;

        $due = $this->dueSlotsBetween($event, $lowerBound, $to);

        if ($due === []) {
            return [];
        }

        $recorded = DB::table(self::TABLE)
            ->where('key', $key)
            ->whereBetween('due_at', [$due[0], $due[count($due) - 1]])
            ->pluck('due_at')
            ->map(static fn ($value): string => Carbon::parse($value)->format('Y-m-d H:i:s'))
            ->all();

        $seen = array_flip($recorded);

        return array_values(array_filter(
            $due,
            static fn (Carbon $slot): bool => ! isset($seen[$slot->format('Y-m-d H:i:s')]),
        ));
    }

    /**
     * Premier créneau observé pour cette entrée — la borne au-delà de laquelle
     * une absence devient une preuve de perte.
     */
    public function firstObservedSlot(string $key): ?Carbon
    {
        $value = DB::table(self::TABLE)->where('key', $key)->min('due_at');

        return $value === null ? null : Carbon::parse($value);
    }

    public function isCatchUpAllowed(string $command): bool
    {
        return array_key_exists($command, self::CATCH_UP_ALLOWLIST);
    }

    /**
     * La table du registre existe-t-elle sur la connexion que ce service
     * utilise réellement ?
     *
     * `Schema::hasTable()` interroge la connexion PAR DÉFAUT. Dans un contexte
     * multi-tenant (base centrale + bases tenant, connexions nommées), ce n'est
     * pas forcément celle où la migration a été jouée : la vérification
     * répondrait « absente » alors que la table est bien là, et le rattrapage
     * s'arrêterait silencieusement. On sonde donc explicitement la connexion du
     * registre.
     */
    public function tableExists(): bool
    {
        return Schema::connection($this->connectionName())->hasTable(self::TABLE);
    }

    /**
     * Nom de la connexion du registre. `DB::table()` ouvrirait une connexion
     * pour lire son nom ; on interroge donc le résolveur directement — même
     * résultat, sans requête ni connexion établie juste pour poser une
     * question de configuration.
     */
    private function connectionName(): string
    {
        return $this->connectionName ??= (string) config('database.default');
    }

    private ?string $connectionName = null;

    public function catchUpLookbackMinutes(string $command): ?int
    {
        return self::CATCH_UP_ALLOWLIST[$command]['lookback'] ?? null;
    }

    public function catchUpReason(string $command): ?string
    {
        return self::CATCH_UP_ALLOWLIST[$command]['reason'] ?? null;
    }

    /**
     * Pourquoi cette commande n'est PAS rattrapée automatiquement (null si elle
     * l'est). Sert au rapport : un opérateur doit pouvoir lire la raison sans
     * fouiller le code.
     */
    public function refusalReason(string $command): ?string
    {
        return self::NEVER_CATCH_UP[$command] ?? null;
    }

    /**
     * @return array<string, array{lookback:int, reason:string}>
     */
    public function allowlist(): array
    {
        return self::CATCH_UP_ALLOWLIST;
    }

    /**
     * @return array<string, string>
     */
    public function denylist(): array
    {
        return self::NEVER_CATCH_UP;
    }

    public function keyFor(Event $event): string
    {
        // Aligné sur la logique de Laravel (`mutexName`) : commande normalisée
        // + expression. Deux entrées distinctes ne partagent donc pas de clé,
        // et la clé survit à un renommage de classe.
        return sha1($event->getExpression().'|'.$this->commandFor($event));
    }

    public function commandFor(Event $event): string
    {
        return $event->getSummaryForDisplay();
    }

    /**
     * Purge des lignes au-delà de la fenêtre de rétention (registre technique,
     * pas une donnée d'audit : 90 jours suffisent à diagnostiquer).
     */
    public function prune(int $days = 90): int
    {
        return DB::table(self::TABLE)
            ->where('due_at', '<', Carbon::now()->subDays($days))
            ->delete();
    }
}
