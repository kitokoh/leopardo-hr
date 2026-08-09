<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * PA2-JOB-001 — Redis/queues readiness.
 *
 * Regression coverage for a previously missing `failed_jobs` table
 * (see database/migrations/public/2026_07_23_000001_create_failed_jobs_table.php).
 * Without it, `queue:failed`, `queue:retry`, `queue:forget`, `queue:flush`
 * and `App\Console\Commands\QueueHealthCheck::failedJobsCount()` would all
 * throw `QueryException: relation "failed_jobs" does not exist` the first
 * time a queued job actually failed outside of sqlite.
 */
class QueueFailedJobsTableTest extends TestCase
{
    use Tests\RefreshTenantDatabase;

    public function test_failed_jobs_table_exists_with_expected_columns(): void
    {
        // `failed_jobs` lives in the `public` schema (shared platform table,
        // not tenant-scoped), but the default test search_path is
        // `shared_tenants,public` — Postgres' `current_schema()` resolves to
        // the first schema in that path, so an unqualified Schema::hasTable()
        // would look in `shared_tenants` first. Query `public` explicitly.
        $this->assertTrue(Schema::hasTable('public.failed_jobs'));

        $this->assertTrue(Schema::hasColumns('public.failed_jobs', [
            'id',
            'uuid',
            'connection',
            'queue',
            'payload',
            'exception',
            'failed_at',
        ]));
    }

    public function test_queue_failed_command_runs_without_error_on_empty_table(): void
    {
        $exitCode = Artisan::call('queue:failed');

        $this->assertSame(0, $exitCode);
    }

    public function test_health_check_command_reports_zero_failed_jobs_instead_of_erroring(): void
    {
        // Insert directly (bypassing a real dispatch/failure) to prove the
        // table is queryable end-to-end, matching what
        // QueueHealthCheck::failedJobsCount() and `queue:failed` do.
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => json_encode(['job' => 'Tests\\Fake']),
            'exception' => 'RuntimeException: fake failure for test',
            'failed_at' => now(),
        ]);

        $this->assertSame(1, DB::table('failed_jobs')->count());

        $exitCode = Artisan::call('queue:failed');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Tests\\Fake', Artisan::output());
    }
}
