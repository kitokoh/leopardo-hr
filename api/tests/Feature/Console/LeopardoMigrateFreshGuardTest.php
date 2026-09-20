<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * Garde d'environnement sur `leopardo:migrate --fresh` (issue #7974) :
 * la commande exécute DROP SCHEMA public/shared_tenants CASCADE — refus
 * sec en production, confirmation interactive ailleurs (sauf --force).
 */
class LeopardoMigrateFreshGuardTest extends TestCase
{
    public function test_fresh_is_refused_in_production_environment(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        // `$this->artisan()` diffère l'exécution au destructeur →
        // Artisan::call pour une exécution synchrone fiable (cf. #5201).
        $exit = Artisan::call('leopardo:migrate', ['--fresh' => true]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString(
            'interdit en production',
            Artisan::output(),
            'Le refus doit être explicite : --fresh détruirait toutes les données (issue #7974).'
        );
    }

    public function test_fresh_is_refused_in_production_even_with_force(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        // Refus quelles que soient les options : --force ne contourne pas
        // la garde de production (issue #7974).
        $exit = Artisan::call('leopardo:migrate', ['--fresh' => true, '--force' => true]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('interdit en production', Artisan::output());
    }

    public function test_fresh_aborts_outside_production_when_confirmation_is_declined(): void
    {
        // Hors production, sans --force : la confirmation est requise. En
        // exécution non interactive, confirm() retourne son défaut (non) →
        // la commande s'arrête AVANT tout DROP SCHEMA (issue #7974).
        $pending = $this->artisan('leopardo:migrate', ['--fresh' => true]);
        assert($pending instanceof PendingCommand);
        $pending->expectsConfirmation(
            '--fresh va supprimer les schemas public et shared_tenants de la base ['
                .(config('database.connections.pgsql.database') ?: config('database.default')).']. Continuer ?',
            'no'
        )
            ->assertExitCode(Command::FAILURE);
    }
}
