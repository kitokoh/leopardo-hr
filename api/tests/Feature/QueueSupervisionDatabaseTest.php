<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5282 — supervision queue sur driver `database` (prod 0 €).
 *
 * `queue:health-check` doit mesurer la profondeur des queues via la table
 * `jobs`, détecter les jobs réservés trop longtemps (worker mort) et sortir
 * en FAILURE quand un seuil est dépassé : c'est le mécanisme qui rend un run
 * `queue-supervision.yml` rouge en < 15 min (DoD #5282).
 */
class QueueSupervisionDatabaseTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.default', 'database');
    }

    public function test_empty_queue_returns_success(): void
    {
        $exitCode = Artisan::call('queue:health-check');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"queue_connection": "database"', Artisan::output());
        $this->assertStringContainsString('"pending_jobs": 0', Artisan::output());
        $this->assertStringContainsString('"stale_reserved_jobs": 0', Artisan::output());
    }

    public function test_pending_backlog_over_threshold_returns_failure(): void
    {
        $now = now()->timestamp;

        for ($i = 0; $i < 3; $i++) {
            $this->insertJob('default', (int) $now, null);
        }

        $exitCode = Artisan::call('queue:health-check', ['--max-pending' => '2']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"pending_jobs": 3', Artisan::output());
    }

    public function test_pending_backlog_within_threshold_returns_success(): void
    {
        $now = now()->timestamp;

        for ($i = 0; $i < 5; $i++) {
            $this->insertJob('notifications', (int) $now, null);
        }

        $exitCode = Artisan::call('queue:health-check', ['--max-pending' => '50']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"pending_jobs": 5', Artisan::output());
    }

    public function test_stale_reserved_job_detects_dead_worker(): void
    {
        // Un job réservé depuis 30 min = worker mort en plein traitement
        // (le drain libère la réservation au pire après quelques minutes).
        $this->insertJob('pdf', (int) now()->subMinutes(40)->timestamp, (int) now()->subMinutes(30)->timestamp);

        $exitCode = Artisan::call('queue:health-check', ['--max-stale-minutes' => '10']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"stale_reserved_jobs": 1', Artisan::output());
    }

    public function test_recently_reserved_job_is_not_stale(): void
    {
        // Un job réservé il y a 2 min est en cours de traitement — pas d'alerte.
        $this->insertJob('default', (int) now()->subMinutes(5)->timestamp, (int) now()->subMinutes(2)->timestamp);

        $exitCode = Artisan::call('queue:health-check', ['--max-stale-minutes' => '10']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"stale_reserved_jobs": 0', Artisan::output());
    }

    public function test_failed_jobs_over_threshold_returns_failure(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['job' => 'Tests\\Fake'], JSON_THROW_ON_ERROR),
            'exception' => 'RuntimeException: fake failure for supervision test',
            'failed_at' => now(),
        ]);

        $exitCode = Artisan::call('queue:health-check', ['--max-failed' => '0']);

        $this->assertSame(1, $exitCode);
    }

    /**
     * Insère un job directement dans la table `jobs` (schéma public).
     */
    private function insertJob(string $queue, int $availableAt, ?int $reservedAt): void
    {
        DB::table('jobs')->insert([
            'queue' => $queue,
            'payload' => json_encode(['job' => 'Tests\\Fake'], JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'reserved_at' => $reservedAt,
            'available_at' => $availableAt,
            'created_at' => $availableAt,
        ]);
    }
}
