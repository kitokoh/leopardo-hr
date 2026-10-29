<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Modules\Platform\Infrastructure\Services\SchedulerRunLedger;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #7967 — rattrapage des tâches planifiées perdues pendant le spin-down.
 *
 * Ces tests portent sur le CONTRAT du rattrapage, pas sur le transport : ils
 * interrogent le registre et la commande directement. C'est délibéré — un test
 * qui passerait par `schedule:run` vérifierait l'ordonnanceur Laravel, pas la
 * garantie qui nous intéresse ici : « un créneau perdu est rejoué une seule
 * fois, et seulement s'il est sûr de l'être ».
 *
 * Le scénario réel reproduit : le conteneur web Render s'endort 4 h,
 * `schedule:run` ne tourne pas, plusieurs échéances de 15 min sont sautées.
 *
 * `scheduler_slot_runs` vit dans le schéma `public` (registre plateforme, comme
 * `scheduled_task_runs` et `failed_jobs`), pas dans un schéma tenant : on ne
 * migre donc que `database/migrations/public`, comme le fait déjà
 * `QueueObservabilityApiTest`. Utiliser `RefreshTenantDatabase` ici forcerait
 * une migration tenant dont ce fichier n'a aucun besoin.
 *
 * Ce que ces tests N'assertent PAS : le contenu métier des commandes rejouées.
 * Le rejeu emprunte `Event::run()`, donc un vrai sous-process artisan — dont le
 * comportement appartient à chaque commande, pas à ce mécanisme. Ce qui est
 * vérifié ici, c'est la réservation idempotente du créneau (`trigger =
 * catch_up`), qui est précisément ce qui empêche un double débit.
 */
class SchedulerCatchUpTest extends TestCase
{
    private SchedulerRunLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        // Forcer `public` en premier : après une classe tenant, une seconde
        // table `migrations` existe dans `shared_tenants` et le Migrator peut
        // résoudre la mauvaise (42P07 sur une migration déjà appliquée).
        DB::statement('SET search_path TO public,shared_tenants');

        $this->artisan('migrate:fresh', [
            '--path' => 'database/migrations/public',
        ]);

        $this->app[Kernel::class]->setArtisan(null);

        $this->ledger = app(SchedulerRunLedger::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── Le créneau enregistré est l'échéance THÉORIQUE, pas l'heure réelle ──

    public function test_due_slot_aligns_on_the_cron_expression_not_the_wall_clock(): void
    {
        // Run à 09:07 pour `0 9 * * *` : le créneau est 09:00. Sans cet
        // alignement, chaque minute créerait une clé différente et la
        // contrainte UNIQUE ne dédoublonnerait plus rien — un rattrapage
        // rejouerait alors les mêmes échéances à chaque redémarrage.
        $event = $this->eventFor('scheduler-test:noop', '0 9 * * *');

        $slot = $this->ledger->dueSlotFor($event, Carbon::parse('2026-10-29 09:07:31'));

        $this->assertSame('2026-10-29 09:00:00', $slot->format('Y-m-d H:i:s'));
    }

    public function test_due_slot_keeps_the_current_slot_when_run_lands_exactly_on_it(): void
    {
        $event = $this->eventFor('scheduler-test:noop', '0 9 * * *');

        $slot = $this->ledger->dueSlotFor($event, Carbon::parse('2026-10-29 09:00:00'));

        $this->assertSame('2026-10-29 09:00:00', $slot->format('Y-m-d H:i:s'));
    }

    // ── Le cœur : dédoublonnage par la base ─────────────────────────────────

    public function test_a_slot_can_only_be_recorded_once(): void
    {
        $event = $this->eventFor('scheduler-test:noop', '0 9 * * *');
        $slot = Carbon::parse('2026-10-29 09:00:00');

        $this->assertTrue($this->ledger->record($event, $slot), 'Premier enregistrement attendu.');
        $this->assertFalse($this->ledger->record($event, $slot), 'Second enregistrement du MÊME créneau doit être refusé.');

        $this->assertSame(1, DB::table('scheduler_slot_runs')->count());
    }

    public function test_two_entries_with_the_same_command_but_different_crons_do_not_collide(): void
    {
        // Deux entrées portant le même nom mais à des fréquences différentes
        // sont des créneaux distincts. Si la clé ne dérivait que du nom, le
        // second écraserait le premier et l'un des deux ne serait jamais
        // rattrapé.
        $hourly = $this->eventFor('scheduler-test:noop', '0 * * * *');
        $daily = $this->eventFor('scheduler-test:noop', '0 9 * * *');

        $this->assertNotSame($this->ledger->keyFor($hourly), $this->ledger->keyFor($daily));
    }

    // ── Détection des créneaux perdus ───────────────────────────────────────

    public function test_missed_slots_reports_every_absent_occurrence_in_the_window(): void
    {
        $event = $this->eventFor('scheduler-test:noop', '*/15 * * * *');

        $from = Carbon::parse('2026-10-29 09:00:00');
        $to = Carbon::parse('2026-10-29 10:00:00');

        // 09:15 et 09:30 ont tourné ; 09:45 et 10:00 sont tombés pendant le
        // spin-down.
        $this->ledger->record($event, Carbon::parse('2026-10-29 09:15:00'));
        $this->ledger->record($event, Carbon::parse('2026-10-29 09:30:00'));

        $missed = $this->ledger->missedSlots($event, $from, $to);

        $this->assertSame(
            ['2026-10-29 09:45:00', '2026-10-29 10:00:00'],
            array_map(static fn (Carbon $slot): string => $slot->format('Y-m-d H:i:s'), $missed),
        );
    }

    public function test_missed_slots_is_empty_when_every_occurrence_was_recorded(): void
    {
        $event = $this->eventFor('scheduler-test:noop', '0 * * * *');

        $from = Carbon::parse('2026-10-29 08:00:00');
        $to = Carbon::parse('2026-10-29 10:00:00');

        foreach (['09:00', '10:00'] as $time) {
            $this->ledger->record($event, Carbon::parse("2026-10-29 {$time}:00"));
        }

        $this->assertSame([], $this->ledger->missedSlots($event, $from, $to));
    }

    // ── Politique : ce qui se rejoue, ce qui ne se rejoue jamais ────────────

    public function test_outbox_drains_and_expiries_are_catch_up_eligible(): void
    {
        foreach ([
            'travel:outbox-dispatch',
            'restaurant:outbox-dispatch',
            'travel:expire-pending-bookings',
            'hospitality:expire-pending-reservations',
        ] as $command) {
            $this->assertTrue(
                $this->ledger->isCatchUpAllowed($command),
                "{$command} doit être rattrapable (drain/expiration, sans effet monétaire)."
            );
        }
    }

    public function test_money_moving_commands_are_never_catch_up_eligible(): void
    {
        // Rejouer une génération de factures trois jours plus tard émet des
        // factures rétroactives avec une date de prestation fausse : c'est un
        // incident comptable. La perte est signalée, jamais réparée à l'aveugle.
        foreach ([
            'billing:generate-invoices',
            'billing:reconcile-payments',
            'payroll:precalculate',
            'travel:settle-sales',
            'leave:accrue',
        ] as $command) {
            $this->assertFalse(
                $this->ledger->isCatchUpAllowed($command),
                "{$command} ne doit JAMAIS être rejouée automatiquement."
            );
            $this->assertNotNull(
                $this->ledger->refusalReason($command),
                "{$command} doit documenter POURQUOI elle n'est pas rattrapable."
            );
        }
    }

    public function test_a_command_outside_both_registries_is_reported_with_a_generic_reason(): void
    {
        $this->assertFalse($this->ledger->isCatchUpAllowed('some:unknown-command'));
        $this->assertNull($this->ledger->refusalReason('some:unknown-command'));
    }

    // ── La commande : réservation des créneaux perdus, une seule fois ───────

    public function test_catch_up_reserves_every_missed_slot_exactly_once(): void
    {
        $this->injectCatchUpEvent('announcements:publish-scheduled', '*/15 * * * *');

        Carbon::setTestNow(Carbon::parse('2026-10-29 10:00:00'));

        // Seul 09:15 est enregistré : 09:30, 09:45 et 10:00 sont perdus.
        $this->ledger->record(
            $this->currentEvent('announcements:publish-scheduled'),
            Carbon::parse('2026-10-29 09:15:00'),
        );

        // @phpstan-ignore-next-line method.nonObject (artisan() → PendingCommand|int)
        $this->artisan('scheduler:catch-up')->assertExitCode(0);

        $catchUpSlots = $this->catchUpSlots('announcements:publish-scheduled');

        $this->assertSame(
            ['2026-10-29 09:30:00', '2026-10-29 09:45:00', '2026-10-29 10:00:00'],
            $catchUpSlots,
            'Les 3 créneaux perdus doivent être réservés en catch_up.',
        );

        // Deuxième passage : plus rien à réserver — la contrainte UNIQUE a
        // couvert les créneaux au premier rejeu. C'est CE point qui garantit
        // qu'une facture ou un débit ne peut pas partir deux fois.
        // @phpstan-ignore-next-line method.nonObject (artisan() → PendingCommand|int)
        $this->artisan('scheduler:catch-up')->assertExitCode(0);

        $this->assertSame(
            $catchUpSlots,
            $this->catchUpSlots('announcements:publish-scheduled'),
            'Un second passage ne doit RIEN réserver de plus.',
        );
    }

    public function test_catch_up_does_not_replay_a_slot_recorded_by_the_normal_scheduler(): void
    {
        $this->injectCatchUpEvent('announcements:publish-scheduled', '*/15 * * * *');

        Carbon::setTestNow(Carbon::parse('2026-10-29 10:00:00'));

        $event = $this->currentEvent('announcements:publish-scheduled');

        // 09:30, 09:45 et 10:00 ont tourné normalement.
        foreach (['09:30', '09:45', '10:00'] as $time) {
            $this->ledger->record($event, Carbon::parse("2026-10-29 {$time}:00"));
        }

        // @phpstan-ignore-next-line method.nonObject (artisan() → PendingCommand|int)
        $this->artisan('scheduler:catch-up')->assertExitCode(0);

        $this->assertSame(
            [],
            $this->catchUpSlots('announcements:publish-scheduled'),
            'Aucun créneau déjà couvert ne doit être rejoué.',
        );
    }

    public function test_dry_run_detects_missed_slots_without_executing_anything(): void
    {
        $this->injectCatchUpEvent('announcements:publish-scheduled', '*/15 * * * *');

        Carbon::setTestNow(Carbon::parse('2026-10-29 10:00:00'));

        // Marqueur d'observation : 09:15 a tourné, donc la fenêtre commence là.
        // Sans lui, « jamais observée » ≠ « perdue » (cf. missedSlots).
        $this->markObserved('announcements:publish-scheduled', '2026-10-29 09:15:00');

        $this->artisan('scheduler:catch-up --dry-run')
            // @phpstan-ignore-next-line method.nonObject (artisan() → PendingCommand|int)
            ->expectsOutputToContain('rattrapables')
            ->assertExitCode(0);

        $this->assertSame(
            1,
            DB::table('scheduler_slot_runs')->count(),
            '--dry-run ne doit rien écrire au-delà du marqueur d\'observation posé par le test.',
        );
    }

    public function test_unrecoverable_missed_slots_fail_loudly_but_the_report_mode_stays_green(): void
    {
        // payroll:precalculate est explicitement NON rattrapable : la commande
        // doit signaler le créneau perdu et sortir en échec, pour qu'un humain
        // tranche — mais --report reste vert (sonde de supervision).
        $this->injectCatchUpEvent('payroll:precalculate', '0 2 * * *');

        Carbon::setTestNow(Carbon::parse('2026-10-29 10:00:00'));

        // 02:00 du 28/10 a tourné ; celui du 29/10 est tombé pendant le
        // spin-down de la nuit.
        $this->markObserved('payroll:precalculate', '2026-10-28 02:00:00');

        $this->artisan('scheduler:catch-up')
            // @phpstan-ignore-next-line method.nonObject (artisan() → PendingCommand|int)
            ->expectsOutputToContain('NON rattrapables')
            ->assertExitCode(1);

        // @phpstan-ignore-next-line method.nonObject (artisan() → PendingCommand|int)
        $this->artisan('scheduler:catch-up --report')->assertExitCode(0);

        $this->assertSame(
            [],
            $this->catchUpSlots('payroll:precalculate'),
            'Une commande non rattrapable ne doit jamais être exécutée.',
        );
    }

    // ── Le registre ne casse jamais la tâche qu'il observe ───────────────────

    public function test_the_recorder_never_throws_and_records_the_current_slot(): void
    {
        $recorder = app(\App\Modules\Platform\Infrastructure\Services\RecordSchedulerRun::class);
        $task = $this->eventFor('scheduler-test:noop', '0 9 * * *');

        Carbon::setTestNow(Carbon::parse('2026-10-29 09:07:00'));

        // Le contrat est « ne lève jamais » : un registre de diagnostic ne doit
        // pas faire échouer la tâche métier qu'il observe. On l'invoque par le
        // listener réel (`ScheduledTaskStarting`), pas par un `__invoke()`
        // fictif — c'est ce chemin-là que la production emprunte.
        $recorder->onStarting(new ScheduledTaskStarting($task));

        $this->assertSame(1, DB::table('scheduler_slot_runs')->count());
        $this->assertSame('scheduled', DB::table('scheduler_slot_runs')->value('trigger'));
        $this->assertSame(
            '2026-10-29 09:00:00',
            Carbon::parse(DB::table('scheduler_slot_runs')->value('due_at'))->format('Y-m-d H:i:s'),
            'Le créneau enregistré doit être l\'échéance cron, pas l\'heure réelle du run.',
        );
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Entrée de planning réaliste (commande console, pas closure) : une closure
     * ne pourrait pas être rejouée hors du process courant, alors que le rejeu
     * réel lance un sous-process artisan.
     */
    private function eventFor(string $command, string $expression): Event
    {
        $event = app(Schedule::class)->exec($command);
        $event->cron($expression);

        return $event;
    }

    /**
     * Injecte une entrée dont le NOM est celui d'une commande de l'allowlist,
     * sans dépendre du planning réel : on teste le mécanisme de rattrapage, pas
     * la liste livrée en production.
     */
    private function injectCatchUpEvent(string $name, string $expression): void
    {
        $event = app(Schedule::class)->exec('scheduler-test:noop');
        $event->cron($expression);
        $event->description($name);
    }

    /**
     * Pose un créneau « scheduled » antérieur : c'est le marqueur qui établit
     * que le registre observait déjà cette entrée. Sans lui, une absence est
     * indistinguable d'un registre neuf (cf. `missedSlots`).
     */
    private function markObserved(string $name, string $slot): void
    {
        $this->ledger->record(
            $this->currentEvent($name),
            Carbon::parse($slot),
        );
    }

    /**
     * Créneaux réservés en catch_up pour cette entrée, triés.
     *
     * @return list<string>
     */
    private function catchUpSlots(string $name): array
    {
        $key = $this->ledger->keyFor($this->currentEvent($name));

        /** @var list<string> $slots */
        $slots = DB::table('scheduler_slot_runs')
            ->where('key', $key)
            ->where('trigger', 'catch_up')
            ->orderBy('due_at')
            ->pluck('due_at')
            ->map(static fn ($value): string => Carbon::parse($value)->format('Y-m-d H:i:s'))
            ->all();

        return $slots;
    }

    private function currentEvent(string $name): Event
    {
        foreach (app(Schedule::class)->events() as $event) {
            if ($event->getSummaryForDisplay() === $name) {
                return $event;
            }
        }

        $this->fail("Entrée de planning « {$name} » introuvable.");
    }
}
