<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * #7974 — `leopardo:migrate --fresh` ne doit JAMAIS pouvoir s'exécuter en
 * production (DROP SCHEMA CASCADE destructeur sur Neon, tous tenants).
 *
 * Garde ajoutée dans routes/console.php :
 *  - env production  → refus sec (exit 1), quelles que soient les options ;
 *  - hors production → confirmation interactive obligatoire sauf --force.
 */
class LeopardoMigrateFreshGuardTest extends TestCase
{
    public function test_fresh_is_hard_refused_in_production(): void
    {
        $this->app->instance('env', 'production');

        $pending = $this->artisan('leopardo:migrate', ['--fresh' => true, '--force' => true]);
        assert($pending instanceof PendingCommand);
        $pending->expectsOutputToContain('INTERDIT en production')
            ->assertExitCode(1);
    }

    public function test_fresh_without_force_aborts_without_interactive_confirmation(): void
    {
        // Sans --force, la confirmation est requise ; non confirmée → abandon.
        $pending = $this->artisan('leopardo:migrate', ['--fresh' => true]);
        assert($pending instanceof PendingCommand);
        $pending->expectsConfirmation(
            '--fresh va DÉTRUIRE les schémas public et shared_tenants sur '.$this->expectedTarget().'. Continuer ?',
            'no'
        )
            ->assertExitCode(1);

        if (DB::getDriverName() === 'pgsql') {
            self::assertTrue(
                Schema::hasTable('migrations'),
                'Abandon sans confirmation : la base ne doit pas avoir été détruite.'
            );
        }
    }

    private function expectedTarget(): string
    {
        $connection = DB::connection()->getConfig();

        return sprintf(
            '%s/%s (env: %s)',
            $connection['host'] ?? '?',
            $connection['database'] ?? '?',
            app()->environment()
        );
    }
}
