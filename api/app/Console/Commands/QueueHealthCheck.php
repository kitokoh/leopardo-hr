<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Notifications\SlackAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Plan 63 — Queue health check command.
 *
 * Usage: php artisan queue:health-check
 * Returns JSON with queue depths for the active driver (redis | database),
 * stale reserved jobs and the failed_jobs count.
 *
 * Issue #5282 — supervision queue : quand un seuil est dépassé
 * (--max-pending / --max-failed / --max-stale-minutes), la commande sort en
 * FAILURE : le workflow `queue-supervision.yml` (cron 5 min) passe alors en
 * échec visible < 15 min après le début d'une panne (DoD #5282), et une
 * alerte Slack opt-in part si SLACK_MONITORING_WEBHOOK_URL est configuré.
 */
class QueueHealthCheck extends Command
{
    protected $signature = 'queue:health-check
        {--queue=* : Specific queue names to check}
        {--max-pending=50 : Exit FAILURE when a queue depth exceeds this (database driver)}
        {--max-failed=10 : Exit FAILURE when failed_jobs exceeds this}
        {--max-stale-minutes=10 : Exit FAILURE when a job stays reserved longer than this (database driver)}';

    protected $description = 'Check queue connectivity and depths (redis | database) — FAILURE si seuil dépassé';

    /** Named queues defined in Plan 63. */
    /** @var list<string> */
    private array $defaultQueues = ['default', 'documents', 'pdf', 'notifications', 'payroll', 'webhooks', 'audit'];

    public function handle(): int
    {
        $driver = (string) config('queue.default', 'sync');

        if ($driver === 'sync') {
            $this->outputResult([
                'status' => 'ok',
                'queue_connection' => 'sync',
            ]);

            return self::SUCCESS;
        }

        /** @var list<string>|null $requestedQueues */
        $requestedQueues = $this->option('queue');
        $queues = $requestedQueues ?: $this->defaultQueues;

        $maxPending = $this->intOption('max-pending', 50);
        $maxFailed = $this->intOption('max-failed', (int) config('services.slack.failed_jobs_threshold', 10));
        $maxStaleMinutes = $this->intOption('max-stale-minutes', 10);

        $result = $driver === 'database'
            ? $this->inspectDatabaseDriver($queues, $maxStaleMinutes)
            : $this->inspectRedisDriver($queues);

        if (($result['status'] ?? 'ok') !== 'error') {
            $result['max_pending'] = $maxPending;
            $result['max_failed'] = $maxFailed;
            $result['max_stale_minutes'] = $maxStaleMinutes;
        }

        $this->notifyIfDegraded($result);

        $this->outputResult($result);

        return $this->thresholdsExceeded($result, $maxPending, $maxFailed)
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * Driver `redis` (Upstash) — latence ping + profondeur par queue.
     *
     * @param  list<string>  $queues
     * @return array<string, mixed>
     */
    private function inspectRedisDriver(array $queues): array
    {
        // Measure Redis round-trip latency
        $start = microtime(true);

        try {
            Redis::connection('default')->ping();
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            $redisOk = true;
        } catch (Throwable $e) {
            $latencyMs = null;
            $redisOk = false;

            return [
                'status' => 'error',
                'redis_ok' => false,
                'redis_latency_ms' => null,
                'error' => $e->getMessage(),
                'queues' => [],
            ];
        }

        $queueStats = [];

        foreach ($queues as $queue) {
            try {
                $length = app('queue')->connection('redis')->size((string) $queue);
                $queueStats[$queue] = [
                    'depth' => (int) $length,
                    'status' => 'ok',
                ];
            } catch (Throwable $e) {
                $queueStats[$queue] = [
                    'depth' => null,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $failedJobs = $this->failedJobsCount();

        return [
            'status' => 'ok',
            'redis_ok' => $redisOk,
            'redis_latency_ms' => $latencyMs,
            'queue_connection' => config('queue.default'),
            'worker_command' => 'php artisan queue:work redis --queue=documents,pdf,payroll,notifications,webhooks,default',
            'failed_jobs' => $failedJobs,
            'queues' => $queueStats,
        ];
    }

    /**
     * Driver `database` (Postgres, prod 0 €) — profondeur par queue via la
     * table `jobs`, jobs réservés trop longtemps (worker mort) et failed_jobs.
     *
     * @param  list<string>  $queues
     * @return array<string, mixed>
     */
    private function inspectDatabaseDriver(array $queues, int $maxStaleMinutes): array
    {
        $table = (string) config('queue.connections.database.table', 'jobs');

        try {
            $depths = $this->databaseQueueDepths($table, $queues);
            $stale = $this->databaseStaleReservedJobs($table, $maxStaleMinutes);
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'queue_connection' => 'database',
                'error' => $e->getMessage(),
                'queues' => [],
            ];
        }

        $queueStats = [];

        foreach ($queues as $queue) {
            $queueStats[$queue] = [
                'depth' => $depths[$queue] ?? 0,
                'status' => 'ok',
            ];
        }

        return [
            'status' => 'ok',
            'queue_connection' => 'database',
            'worker_command' => 'php artisan queue:work database --queue='.implode(',', $queues).' --tries=3 --timeout=280 --stop-when-empty',
            'pending_jobs' => array_sum($depths),
            'stale_reserved_jobs' => $stale,
            'failed_jobs' => $this->failedJobsCount(),
            'queues' => $queueStats,
        ];
    }

    /**
     * Profondeur par queue : jobs prêts ou en attente (non réservés, échéance
     * atteinte). Un worker mort laisse cette profondeur croître → détection.
     *
     * @param  list<string>  $queues
     * @return array<string, int>
     */
    private function databaseQueueDepths(string $table, array $queues): array
    {
        $now = now()->timestamp;

        $rows = DB::table($table)
            ->select('queue', DB::raw('COUNT(*) as cnt'))
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $now)
            ->whereIn('queue', $queues)
            ->groupBy('queue')
            ->get();

        $depths = [];

        foreach ($rows as $row) {
            $depths[(string) $row->queue] = (int) $row->cnt;
        }

        foreach ($queues as $queue) {
            $depths[$queue] ??= 0;
        }

        return $depths;
    }

    /**
     * Jobs réservés depuis plus de $maxStaleMinutes : signe d'un worker mort
     * (le drain GH Actions réserve les jobs pendant leur traitement ; aucun
     * process sain ne laisse un job réservé plus de quelques minutes).
     */
    private function databaseStaleReservedJobs(string $table, int $maxStaleMinutes): int
    {
        $threshold = now()->subMinutes($maxStaleMinutes)->timestamp;

        return (int) DB::table($table)
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $threshold)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function outputResult(array $data): void
    {
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * Plan 63 / audit PM 2026-08-17 / issue #5282 — alerte Slack opt-in sur
     * dégradation observée (Redis injoignable, queue en erreur, pending ou
     * stale au-dessus des seuils, failed_jobs au-dessus du seuil). Ne fait
     * rien tant que SLACK_MONITORING_WEBHOOK_URL n'est pas configuré.
     *
     * @param  array<string, mixed>  $result
     */
    private function notifyIfDegraded(array $result): void
    {
        $webhook = (string) config('services.slack.monitoring_webhook');

        if ($webhook === '') {
            return;
        }

        $status = (string) ($result['status'] ?? 'ok');
        $redisOk = (bool) ($result['redis_ok'] ?? true);
        $failedRaw = $result['failed_jobs'] ?? 0;
        $failedJobs = is_int($failedRaw) || is_numeric($failedRaw) ? (int) $failedRaw : 0;
        $threshold = (int) config('services.slack.failed_jobs_threshold', 10);
        $stale = (int) ($result['stale_reserved_jobs'] ?? 0);
        $pending = (int) ($result['pending_jobs'] ?? 0);
        $maxPending = (int) ($result['max_pending'] ?? 0);

        $queuesInError = [];

        /** @var array<string, array{status?: string}> $queues */
        $queues = (array) ($result['queues'] ?? []);
        foreach ($queues as $name => $stats) {
            if (($stats['status'] ?? 'ok') === 'error') {
                $queuesInError[] = (string) $name;
            }
        }

        if ($status === 'ok' && $redisOk && $queuesInError === [] && $failedJobs <= $threshold && $stale === 0 && $pending <= $maxPending) {
            return;
        }

        Notification::route('slack', $webhook)->notify(new SlackAlertNotification(
            message: 'Queue/Redis health dégradé (queue:health-check)',
            severity: 'critical',
            context: [
                'status' => $status,
                'redis_ok' => $redisOk,
                'queues_en_erreur' => $queuesInError,
                'pending_jobs' => $pending,
                'max_pending' => $maxPending,
                'stale_reserved_jobs' => $stale,
                'failed_jobs' => $failedJobs,
                'failed_jobs_threshold' => $threshold,
            ],
        ));
    }

    private function failedJobsCount(): ?int
    {
        try {
            $table = (string) config('queue.failed.table', 'failed_jobs');

            return DB::table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function thresholdsExceeded(array $result, int $maxPending, int $maxFailed): bool
    {
        if (($result['status'] ?? 'ok') === 'error') {
            return true;
        }

        /** @var array<string, array{status?: string}> $queues */
        $queues = (array) ($result['queues'] ?? []);
        foreach ($queues as $stats) {
            if (($stats['status'] ?? 'ok') === 'error') {
                return true;
            }
        }

        $pending = (int) ($result['pending_jobs'] ?? 0);
        if ($pending > $maxPending) {
            return true;
        }

        $stale = (int) ($result['stale_reserved_jobs'] ?? 0);
        if ($stale > 0) {
            return true;
        }

        $failedRaw = $result['failed_jobs'] ?? 0;
        $failedJobs = is_int($failedRaw) || is_numeric($failedRaw) ? (int) $failedRaw : 0;

        return $failedJobs > $maxFailed;
    }

    private function intOption(string $key, int $default): int
    {
        $value = $this->option($key);

        if (is_string($value) && $value !== '') {
            return (int) $value;
        }

        return $default;
    }
}
