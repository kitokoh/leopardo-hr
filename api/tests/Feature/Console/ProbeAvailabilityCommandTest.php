<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Décision 2026-08-21 — drivers « meilleur → pire » selon la disponibilité :
 * cache/session Redis (Upstash) si joignable, sinon file ; queue toujours
 * database (pas de quota, drain GitHub Actions #5204/#5205).
 */
class ProbeAvailabilityCommandTest extends TestCase
{
    public function test_json_format_returns_success(): void
    {
        // `$this->artisan()` renvoie PendingCommand|int et n'exécute la
        // commande qu'au destructeur (assertions sur un mock de sortie vide)
        // → Artisan::call pour une exécution synchrone fiable (issue #5201).
        $exit = Artisan::call('infra:probe-availability');

        $this->assertSame(0, $exit);
    }

    public function test_env_format_pins_database_queue(): void
    {
        // La queue est volontairement FIXE sur database : c'est le « meilleur »
        // choix à vie (pas de quota, drainable par le worker GH Actions).
        $exit = Artisan::call('infra:probe-availability', ['--format' => 'env']);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString(
            'QUEUE_CONNECTION=database',
            Artisan::output(),
            'La queue doit rester sur database (drain GH Actions #5204).'
        );
    }

    public function test_env_format_recommends_redis_or_file_for_cache(): void
    {
        // Déterministe : la valeur dépend de la disponibilité réelle de Redis,
        // mais elle doit être l'une des deux seules options du failover.
        // `expectsOutputToMatch` n'existe pas sur PendingCommand (assertion
        // Pest), et `Artisan::output()` est VIDE quand la commande tourne via
        // le mock de sortie de `$this->artisan()` → on passe par Artisan::call
        // pour récupérer la sortie réelle (issue #5201).
        $exit = Artisan::call('infra:probe-availability', ['--format' => 'env']);

        $this->assertSame(0, $exit);
        $this->assertMatchesRegularExpression(
            '/^CACHE_STORE=(redis|file)$/m',
            Artisan::output(),
            'CACHE_STORE doit être redis ou file (failover binaire).'
        );
    }
}
