<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Modules\Platform\Domain\Models\ScheduledTaskRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * PA2-QA-006 — Observabilite Redis/jobs.
 *
 * Aggregates everything a super-admin needs to answer "are the background
 * jobs healthy right now?" without SSH-ing into the box:
 *   - Redis connectivity/latency (reuses the same probe as HealthController)
 *   - per-queue depth (documents, pdf, payroll, notifications, webhooks, default)
 *   - failed_jobs count + the most recent failures (queue, exception, when)
 *   - last run (started/finished/status) of every scheduled Artisan command
 *
 * Every sub-check is wrapped so a single broken probe (e.g. Redis down)
 * degrades that section instead of taking the whole endpoint down with a 500.
 */
class QueueObservabilityService
{
    /** Named queues also used by QueueHealthCheck / HealthController. */
    private const QUEUES = ['default', 'documents', 'pdf', 'payroll', 'notifications', 'webhooks'];

    /**
     * Failed-jobs count above this threshold flips `alerts.failed_jobs` to true.
     */
    private const FAILED_JOBS_ALERT_THRESHOLD = 10;

    /**
     * Any single queue depth above this threshold flips `alerts.queue_depth` to true.
     */
    private const QUEUE_DEPTH_ALERT_THRESHOLD = 500;

    /**
     * A scheduled task whose last run is older than this (and has a known
     * schedule) is considered stale and flips `alerts.stale_tasks` to true.
     */
    private const STALE_TASK_ALERT_HOURS = 26;

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $redis = $this->checkRedis();
        $queues = $this->queueDepths();
        $failedJobs = $this->failedJobs();
        $scheduledTasks = $this->scheduledTasks();

        $totalDepth = array_sum(array_column($queues, 'depth'));
        $maxDepth = $queues === [] ? 0 : max(array_column($queues, 'depth'));

        $staleTasks = array_values(array_filter(
            $scheduledTasks,
            static fn (array $task): bool => $task['is_stale'] === true,
        ));

        return [
            'redis' => $redis,
            'queue_connection' => (string) config('queue.default'),
            'queues' => $queues,
            'queue_total_depth' => $totalDepth,
            'failed_jobs' => $failedJobs,
            'scheduled_tasks' => $scheduledTasks,
            'alerts' => [
                'redis_down' => $redis['ok'] === false,
                'queue_depth' => $maxDepth >= self::QUEUE_DEPTH_ALERT_THRESHOLD,
                'failed_jobs' => $failedJobs['count'] >= self::FAILED_JOBS_ALERT_THRESHOLD,
                'stale_tasks' => $staleTasks !== [],
            ],
            'thresholds' => [
                'failed_jobs' => self::FAILED_JOBS_ALERT_THRESHOLD,
                'queue_depth' => self::QUEUE_DEPTH_ALERT_THRESHOLD,
                'stale_task_hours' => self::STALE_TASK_ALERT_HOURS,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{ok: bool, status?: string, latency_ms?: int, error?: string}
     */
    private function checkRedis(): array
    {
        $start = microtime(true);

        try {
            $response = Redis::connection()->ping();
            $ok = $response === true || $response === 'PONG' || $response === '+PONG';

            return [
                'ok' => $ok,
                'status' => $ok ? 'pong' : 'unexpected',
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'status' => 'unreachable',
                'error' => class_basename($e),
            ];
        }
    }

    /**
     * @return array<int, array{name: string, depth: int, ok: bool}>
     */
    private function queueDepths(): array
    {
        $driver = (string) config('queue.default', 'sync');

        if ($driver === 'sync') {
            return array_map(
                static fn (string $name): array => ['name' => $name, 'depth' => 0, 'ok' => true],
                self::QUEUES,
            );
        }

        $queues = [];

        foreach (self::QUEUES as $queue) {
            try {
                $depth = (int) app('queue')->connection()->size($queue);
                $queues[] = ['name' => $queue, 'depth' => $depth, 'ok' => true];
            } catch (Throwable) {
                $queues[] = ['name' => $queue, 'depth' => 0, 'ok' => false];
            }
        }

        return $queues;
    }

    /**
     * @return array{count: int|null, recent: array<int, array{id: int, queue: string, exception: string, failed_at: string|null}>}
     */
    private function failedJobs(): array
    {
        try {
            $table = (string) config('queue.failed.table', 'failed_jobs');

            if (! Schema::hasTable($table)) {
                return ['count' => null, 'recent' => []];
            }

            $count = (int) DB::table($table)->count();

            $recent = DB::table($table)
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'queue', 'exception', 'failed_at'])
                ->map(function (object $row): array {
                    return [
                        'id' => (int) $row->id,
                        'queue' => (string) $row->queue,
                        // First line only: full stack traces are large and
                        // this is a dashboard summary, not a log viewer.
                        'exception' => $this->truncateFirstLine((string) $row->exception),
                        'failed_at' => $row->failed_at !== null ? Carbon::parse($row->failed_at)->toIso8601String() : null,
                    ];
                })
                ->all();

            return ['count' => $count, 'recent' => $recent];
        } catch (Throwable) {
            return ['count' => null, 'recent' => []];
        }
    }

    /**
     * Returns only the first non-empty line of a stack-trace-shaped string,
     * capped to a dashboard-safe length.
     */
    private function truncateFirstLine(string $value): string
    {
        $firstLine = trim(strtok($value, "\n") ?: '');

        return mb_strlen($firstLine) > 300 ? mb_substr($firstLine, 0, 300).'…' : $firstLine;
    }

    /**
     * @return array<int, array{name: string, started_at: string|null, finished_at: string|null, status: string, runtime_ms: int|null, exit_code: int|null, is_stale: bool}>
     */
    private function scheduledTasks(): array
    {
        try {
            if (! Schema::hasTable('scheduled_task_runs')) {
                return [];
            }

            DB::statement('SET search_path TO public');

            return ScheduledTaskRun::query()
                ->orderBy('name')
                ->get()
                ->map(function (ScheduledTaskRun $run): array {
                    $reference = $run->finished_at ?? $run->started_at;
                    $isStale = $reference !== null
                        && $reference->lt(now()->subHours(self::STALE_TASK_ALERT_HOURS));

                    return [
                        'name' => $run->name,
                        'started_at' => $run->started_at?->toIso8601String(),
                        'finished_at' => $run->finished_at?->toIso8601String(),
                        'status' => $run->status,
                        'runtime_ms' => $run->runtime_ms,
                        'exit_code' => $run->exit_code,
                        'is_stale' => $isStale,
                    ];
                })
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
