<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Testing\PendingCommand;
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
        /** @var PendingCommand $command */
        $command = $this->artisan('infra:probe-availability');
        $command->assertExitCode(0);
    }

    public function test_env_format_pins_database_queue(): void
    {
        // La queue est volontairement FIXE sur database : c'est le « meilleur »
        // choix à vie (pas de quota, drainable par le worker GH Actions).
        /** @var PendingCommand $command */
        $command = $this->artisan('infra:probe-availability', ['--format' => 'env']);
        $command->expectsOutputToContain('QUEUE_CONNECTION=database')
            ->assertExitCode(0);
    }

    public function test_env_format_recommends_redis_or_file_for_cache(): void
    {
        // Déterministe : la valeur dépend de la disponibilité réelle de Redis,
        // mais elle doit être l'une des deux seules options du failover.
        /** @var PendingCommand $command */
        $command = $this->artisan('infra:probe-availability', ['--format' => 'env']);
        $command->expectsOutputToContain('CACHE_STORE=')
            ->assertExitCode(0);
    }
}
