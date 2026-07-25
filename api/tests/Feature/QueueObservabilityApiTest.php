<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Platform\Domain\Models\ScheduledTaskRun;
use App\Modules\Platform\Infrastructure\Services\ScheduledTaskRunRecorder;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Contracts\Console\Kernel;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PA2-QA-006 — Observabilite Redis/jobs.
 *
 * Covers GET /api/v1/platform/observability/queues (queue depth, failed
 * jobs, scheduled task last-run, derived alerts) and the
 * `ScheduledTaskRunRecorder` that feeds the `scheduled_task_runs` table
 * from the `schedule:run` console events.
 *
 * `failed_jobs`, `scheduled_task_runs` and `super_admins` all live in the
 * `public` schema (platform-wide, not tenant-scoped) — unlike
 * `Tests\RefreshTenantDatabase`, this only migrates `database/migrations/
 * public`, skipping the tenant migration path entirely since nothing under
 * test needs a tenant schema.
 */
class QueueObservabilityApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', [
            '--path' => 'database/migrations/public',
        ]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    public function test_requires_super_admin_authentication(): void
    {
        $response = $this->getJson('/api/v1/platform/observability/queues');

        $response->assertUnauthorized();
    }

    public function test_super_admin_can_view_queue_observability_snapshot(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => json_encode(['job' => 'Tests\\Fake']),
            'exception' => "RuntimeException: fake failure\n#0 fake trace line",
            'failed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/platform/observability/queues');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'redis',
                'queue_connection',
                'queues',
                'queue_total_depth',
                'failed_jobs' => ['count', 'recent'],
                'scheduled_tasks',
                'alerts' => ['redis_down', 'queue_depth', 'failed_jobs', 'stale_tasks'],
                'thresholds',
                'generated_at',
            ],
        ]);
        $response->assertJsonPath('data.failed_jobs.count', 1);
        $response->assertJsonPath('data.failed_jobs.recent.0.queue', 'default');
        $response->assertJsonPath('data.failed_jobs.recent.0.exception', 'RuntimeException: fake failure');
    }

    public function test_recorder_persists_last_run_status_of_scheduled_tasks(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        Carbon::setTestNow(Carbon::parse('2026-07-25 03:00:00', 'UTC'));

        $recorder = app(ScheduledTaskRunRecorder::class);

        $successTask = $this->fakeScheduledTask('billing:check-trials', 0);
        $successName = $successTask->getSummaryForDisplay();
        $recorder->onStarting(new ScheduledTaskStarting($successTask));
        $recorder->onFinished(new ScheduledTaskFinished($successTask, 1.25));

        $failedTask = $this->fakeScheduledTask('billing:generate-invoices', 1);
        $failedName = $failedTask->getSummaryForDisplay();
        $recorder->onStarting(new ScheduledTaskStarting($failedTask));
        $recorder->onFailed(new ScheduledTaskFailed($failedTask, new \RuntimeException('boom')));

        $this->assertSame(2, ScheduledTaskRun::query()->count());

        $success = ScheduledTaskRun::query()->where('name', $successName)->first();
        $this->assertNotNull($success);
        $this->assertSame(ScheduledTaskRun::STATUS_SUCCESS, $success->status);
        $this->assertSame(0, $success->exit_code);

        $failed = ScheduledTaskRun::query()->where('name', $failedName)->first();
        $this->assertNotNull($failed);
        $this->assertSame(ScheduledTaskRun::STATUS_FAILED, $failed->status);

        $response = $this->getJson('/api/v1/platform/observability/queues');
        $response->assertOk();

        $names = collect($response->json('data.scheduled_tasks'))->pluck('name');
        $this->assertTrue($names->contains($successName));
        $this->assertTrue($names->contains($failedName));

        $failedEntry = collect($response->json('data.scheduled_tasks'))
            ->firstWhere('name', $failedName);
        $this->assertSame('failed', $failedEntry['status']);

        Carbon::setTestNow();
    }

    public function test_stale_scheduled_task_flips_alert(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        ScheduledTaskRun::query()->create([
            'name' => 'contracts:alert-expiring',
            'started_at' => now()->subHours(30),
            'finished_at' => now()->subHours(30),
            'status' => ScheduledTaskRun::STATUS_SUCCESS,
            'exit_code' => 0,
        ]);

        $response = $this->getJson('/api/v1/platform/observability/queues');

        $response->assertOk();
        $response->assertJsonPath('data.alerts.stale_tasks', true);

        $entry = collect($response->json('data.scheduled_tasks'))
            ->firstWhere('name', 'contracts:alert-expiring');
        $this->assertTrue($entry['is_stale']);
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password123'),
        ]);
    }

    /**
     * Builds a real `Illuminate\Console\Scheduling\Event` instance (the
     * class the framework events are strictly type-hinted against) without
     * going through a full `schedule:run` cycle.
     */
    private function fakeScheduledTask(string $command, int $exitCode): Event
    {
        $task = new Event(app(CacheEventMutex::class), $command);
        $task->exitCode = $exitCode;

        return $task;
    }
}
