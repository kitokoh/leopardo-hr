<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * BOS-006A (#8139) — Source unique de planification du scheduler.
 *
 * Le scheduler Laravel était défini DEUX fois (`bootstrap/app.php` via
 * `withSchedule` ET `routes/console.php`) : Laravel charge les deux, donc
 * 8 commandes tournaient en double avec des horaires contradictoires —
 * dont `leave:accrue` (daily + monthly → double acquisition le 1er du mois,
 * la commande n'étant pas idempotente sur la période) et
 * `billing:generate-invoices` (02:00 + 03:00 → factures en double).
 *
 * `routes/console.php` est désormais la SEULE source. Ce test verrouille :
 *  1. l'absence de doublon (nom de commande ET signature complète) ;
 *  2. les fréquences canoniques des 8 commandes auparavant dupliquées ;
 *  3. le verrou anti-chevauchement des commandes à effets ;
 *  4. la disparition de l'expireur Travel legacy `travel:expire-bookings`.
 */
class SchedulerSingleSourceTest extends TestCase
{
    /**
     * Charge le scheduler exactement comme le fait le bootstrap console
     * (`routes/console.php` est requis au premier appel Artisan) puis mappe
     * chaque commande planifiée à ses attributs.
     *
     * @return array<string, array{expression: string, withoutOverlapping: bool, onOneServer: bool}>
     *               indexé par la signature artisan complète (ex. « leave:accrue », « billing:report --json »)
     */
    private function scheduledCommands(): array
    {
        // Force le boot du kernel console → routes/console.php est chargé
        // (même mécanisme que `php artisan schedule:list` en production).
        $this->assertSame(0, Artisan::call('schedule:list'), 'schedule:list doit s\'exécuter sans erreur.');

        $map = [];

        foreach (app(Schedule::class)->events() as $event) {
            $signature = $this->artisanSignature((string) $event->command);

            if ($signature === null) {
                // Toute entrée planifiée doit être une commande artisan
                // identifiable (pas de closure/opaque) : sinon, impossible de
                // garantir l'unicité — échec explicite.
                $this->fail('Entrée planifiée non identifiable comme commande artisan : '.$event->command);
            }

            $map[$signature] = [
                'expression' => $event->expression,
                'withoutOverlapping' => (bool) $event->withoutOverlapping,
                'onOneServer' => (bool) $event->onOneServer,
            ];
        }

        return $map;
    }

    /**
     * Extrait « leave:accrue » / « billing:report --json » de la commande
     * exec complète (`'<php>' 'artisan' <signature>`).
     */
    private function artisanSignature(string $command): ?string
    {
        if (preg_match("/[^\s']*artisan'?\s+(?<cmd>[a-z][a-z0-9:_-]+(?:\s+--?[a-z0-9][\w=-]*)*)/i", $command, $m) !== 1) {
            return null;
        }

        return trim((string) $m['cmd']);
    }

    public function test_no_command_is_scheduled_twice(): void
    {
        $commands = $this->scheduledCommands();

        $this->assertNotEmpty($commands, 'Le scheduler ne contient aucune entrée — routes/console.php non chargé ?');

        // Unicité sur la signature complète ET sur le nom nu : deux entrées
        // pour la même commande (même avec des arguments différents) sont le
        // défaut exact de #8139.
        $names = array_map(
            static fn (string $signature): string => explode(' ', $signature, 2)[0],
            array_keys($commands),
        );

        $duplicates = array_keys(array_filter(
            array_count_values($names),
            static fn (int $count): bool => $count > 1,
        ));

        $this->assertSame(
            [],
            $duplicates,
            'Commande(s) planifiée(s) en double (double scheduler #8139) : '.implode(', ', $duplicates),
        );
    }

    public function test_canonical_frequencies_of_previously_duplicated_commands(): void
    {
        $commands = $this->scheduledCommands();

        // Fréquences canoniques actées lors de l'unification (#8139) — les
        // variantes contradictoires de bootstrap/app.php ont été supprimées.
        $expected = [
            // mensuel le 1er à 03:00 (la commande saute hors 1er du mois ;
            // l'ancien doublon daily provoquait une double acquisition)
            'leave:accrue' => '0 3 1 * *',
            // annuel le 1er janvier à 04:00 (après l'acquisition de 03:00)
            'leave:carry-forward' => '0 4 1 1 *',
            // notifications métier du matin (et non 00:00)
            'contracts:alert-expiring' => '0 7 * * *',
            'billing:check-trials' => '0 8 * * *',
            'billing:check-overdue' => '0 9 * * *',
            // une seule génération mensuelle de factures (et non 02:00 + 03:00)
            'billing:generate-invoices' => '0 2 1 * *',
            // fermeture horaire unique, avec verrous (ADR-0016 Phase 4, #5355)
            'attendance:auto-close' => '0 * * * *',
            // expiration unique des réservations (5 min)
            'travel:expire-pending-bookings' => '*/5 * * * *',
        ];

        foreach ($expected as $name => $expression) {
            $signature = $this->findByName($commands, $name);

            $this->assertNotNull($signature, "Entrée planifiée manquante : {$name}");
            $this->assertSame(
                $expression,
                $commands[$signature]['expression'],
                "Fréquence non canonique pour {$name} (signature « {$signature} »)",
            );
        }
    }

    public function test_sensitive_commands_have_overlapping_lock(): void
    {
        $commands = $this->scheduledCommands();

        // Règle #8139 : toute commande à effets (écritures, notifications,
        // emails, webhooks, provisioning, purge, sync) porte
        // `withoutOverlapping()`. Seul exempt documenté :
        // `monitor:slow-queries` (monitor en lecture seule).
        $sensitive = [
            'billing:check-trials', 'billing:check-overdue', 'billing:generate-invoices',
            'billing:reconcile-payments', 'billing:report', 'app:send-drip-emails',
            'leave:accrue', 'leave:carry-forward', 'contracts:alert-expiring',
            'attendance:auto-close', 'payroll:precalculate', 'manager:weekly-digest',
            'onboarding:send-reminders', 'islamic:check-unconfirmed',
            'travel:outbox-dispatch', 'travel:settle-sales', 'travel:expire-pending-bookings',
            'hospitality:expire-pending-reservations', 'travel:expire-adverts',
            'travel:webhook-dispatch', 'travel:rebuild-report-readmodels',
            'fuel:outbox-dispatch', 'fuel:alerts-dispatch', 'restaurant:outbox-dispatch',
            'crm:process-campaign-sends', 'crm:tasks:send-overdue-reminders',
            'communication:sync-mailboxes', 'communication:send-follow-ups',
            'marketing:publish-scheduled-posts', 'announcements:publish-scheduled',
            'growth:approve-commissions', 'growth:archive-clicks',
            'sanctum:prune-expired', 'queue:health-check', 'trial-provisionings:sweep',
            'edge:monitor', 'tts:purge', 'accounting:purge-expired-shares',
            'leopardo:fleet:sync', 'audit:purge', 'biometric:purge-expired',
        ];

        foreach ($sensitive as $name) {
            $signature = $this->findByName($commands, $name);

            $this->assertNotNull($signature, "Entrée planifiée manquante : {$name}");
            $this->assertTrue(
                $commands[$signature]['withoutOverlapping'],
                "{$name} est une commande à effets : withoutOverlapping() requis (#8139)",
            );
        }

        // Exempt documenté : monitor en lecture seule.
        $monitor = $this->findByName($commands, 'monitor:slow-queries');
        $this->assertNotNull($monitor, 'Entrée planifiée manquante : monitor:slow-queries');
        $this->assertFalse(
            $commands[$monitor]['withoutOverlapping'],
            'monitor:slow-queries est le seul exempt documenté (lecture seule) — ne pas ajouter de verrou sans raison',
        );
    }

    public function test_minute_dispatchers_run_on_one_server(): void
    {
        $commands = $this->scheduledCommands();

        $dispatchers = [
            'travel:outbox-dispatch', 'travel:webhook-dispatch',
            'fuel:outbox-dispatch', 'restaurant:outbox-dispatch',
            'travel:expire-pending-bookings', 'hospitality:expire-pending-reservations',
            'attendance:auto-close',
        ];

        foreach ($dispatchers as $name) {
            $signature = $this->findByName($commands, $name);

            $this->assertNotNull($signature, "Entrée planifiée manquante : {$name}");
            $this->assertTrue(
                $commands[$signature]['onOneServer'],
                "{$name} doit tourner sur un seul serveur (#8139)",
            );
        }
    }

    public function test_legacy_travel_expire_bookings_command_is_gone(): void
    {
        $commands = $this->scheduledCommands();

        // Plus aucune planification de l'expireur legacy…
        $this->assertNull(
            $this->findByName($commands, 'travel:expire-bookings'),
            'L\'expireur legacy travel:expire-bookings ne doit plus être planifié (#8139)',
        );
        $this->assertNotNull(
            $this->findByName($commands, 'travel:expire-pending-bookings'),
            'L\'expireur canonique travel:expire-pending-bookings doit rester planifié',
        );

        // … ni commande artisan enregistrée…
        $this->assertArrayNotHasKey(
            'travel:expire-bookings',
            Artisan::all(),
            'La commande legacy travel:expire-bookings ne doit plus exister (#8139)',
        );

        // … ni classe (littéral : la classe n'existe plus, `::class`
        // déclencherait une erreur d'analyse statique à juste titre).
        $this->assertFalse(
            class_exists('App\\Console\\Commands\\TravelExpireBookingsCommand'),
            'TravelExpireBookingsCommand doit être supprimée (#8139)',
        );
    }

    public function test_schedule_list_shows_no_duplicate_and_no_legacy(): void
    {
        // CA #8139 : `php artisan schedule:list` ne montre aucune commande
        // en double ni l'expireur legacy.
        $this->assertSame(0, Artisan::call('schedule:list'));

        $output = Artisan::output();

        $this->assertStringContainsString('leave:accrue', $output);
        $this->assertStringContainsString('travel:expire-pending-bookings', $output);
        // « travel:expire-pending-bookings » ne contient PAS la sous-chaîne
        // « travel:expire-bookings » : la présence de cette sous-chaîne
        // signerait le retour du legacy.
        $this->assertStringNotContainsString('travel:expire-bookings', $output);
    }

    /**
     * @param  array<string, array{expression: string, withoutOverlapping: bool, onOneServer: bool}>  $commands
     */
    private function findByName(array $commands, string $name): ?string
    {
        foreach (array_keys($commands) as $signature) {
            if (explode(' ', $signature, 2)[0] === $name) {
                return $signature;
            }
        }

        return null;
    }
}
