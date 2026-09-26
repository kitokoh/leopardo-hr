<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * BOS-006A (#8139) — le scheduler a UNE seule source de planification.
 *
 * Le scheduler était défini deux fois (`bootstrap/app.php` withSchedule +
 * `routes/console.php`) : 8 commandes tournaient en double avec des horaires
 * contradictoires (double acquisition de congés, double génération de
 * factures, deux expireurs Travel publiant deux événements différents).
 *
 * Ces tests introspectent le VRAI planning (`schedule:list`) : ils étaient
 * rouges avant la consolidation et verrouillent la non-régression.
 */
class SchedulerUniquenessTest extends TestCase
{
    public function test_scheduled_commands_are_unique(): void
    {
        $names = $this->scheduledCommandNames();

        $this->assertNotEmpty($names, 'Le planning doit exposer des commandes planifiées');

        $duplicates = array_keys(array_filter(
            array_count_values($names),
            static fn (int $count): bool => $count > 1,
        ));

        $this->assertSame(
            [],
            $duplicates,
            'Commandes planifiées en DOUBLE (une seule source de planification autorisée) : '.implode(', ', $duplicates)
        );
    }

    public function test_legacy_travel_expirer_is_removed(): void
    {
        $names = $this->scheduledCommandNames();

        // Le legacy publiait `travel.booking.expired.v1` en concurrence avec
        // `travel.booking.cancelled.v1` du canonique : plus de classe, plus de
        // commande enregistrée, plus de planification → un seul expireur Travel.
        $this->assertFileDoesNotExist(
            app_path('Console/Commands/TravelExpireBookingsCommand.php'),
            'TravelExpireBookingsCommand (legacy) doit être supprimé (#8139)'
        );
        $this->assertArrayNotHasKey('travel:expire-bookings', Artisan::all());
        $this->assertNotContains('travel:expire-bookings', $names);

        $expirers = array_values(array_filter(
            $names,
            static fn (string $name): bool => in_array(
                $name,
                ['travel:expire-bookings', 'travel:expire-pending-bookings'],
                true,
            ),
        ));

        $this->assertSame(['travel:expire-pending-bookings'], $expirers);
    }

    public function test_sensitive_commands_carry_the_overlap_lock(): void
    {
        // Peuple le scheduler (même chemin que `schedule:list`).
        $this->scheduledCommandNames();

        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);

        $locked = [];
        foreach ($schedule->events() as $event) {
            if (! $event->withoutOverlapping) {
                continue;
            }

            $name = $this->commandName($event);
            if ($name !== null) {
                $locked[] = $name;
            }
        }

        $sensitive = [
            'leave:accrue',
            'leave:carry-forward',
            'contracts:alert-expiring',
            'billing:check-trials',
            'billing:check-overdue',
            'billing:generate-invoices',
            'attendance:auto-close',
            'travel:expire-pending-bookings',
        ];

        foreach ($sensitive as $command) {
            $this->assertContains($command, $locked, "{$command} doit planifier withOverlapping()");
        }
    }

    /**
     * Noms de commandes artisan réellement planifiés (une entrée par ligne de
     * `schedule:list`, options exclues).
     *
     * @return list<string>
     */
    private function scheduledCommandNames(): array
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $names = [];
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match('/php artisan ([a-z0-9:_-]+)/i', $line, $matches) === 1) {
                $names[] = $matches[1];
            }
        }

        return $names;
    }

    private function commandName(Event $event): ?string
    {
        $command = (string) $event->command;

        if (preg_match('/artisan[\'"]?\s+[\'"]?([a-z0-9:_-]+)/i', $command, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
