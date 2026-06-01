<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Plan 63 — Queue health check command.
 *
 * Usage: php artisan queue:health-check
 * Returns JSON with Redis latency and queue depths for all named queues.
 */
class QueueHealthCheck extends Command
{
    protected $signature = 'queue:health-check {--queue=* : Specific queue names to check}';

    protected $description = 'Check Redis connectivity and queue depths (Upstash-compatible)';

    /** Named queues defined in Plan 63. */
    private array $defaultQueues = ['default', 'documents', 'pdf', 'notifications', 'payroll', 'webhooks'];

    public function handle(): int
    {
        $queues = $this->option('queue') ?: $this->defaultQueues;

        // Measure Redis round-trip latency
        $start = microtime(true);
        $ping = null;

        try {
            $ping = Redis::connection('default')->ping();
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            $redisOk = true;
        } catch (Throwable $e) {
            $latencyMs = null;
            $redisOk = false;
            $this->outputResult([
                'status' => 'error',
                'redis_ok' => false,
                'redis_latency_ms' => null,
                'error' => $e->getMessage(),
                'queues' => [],
            ]);

            return self::FAILURE;
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

        $result = [
            'status' => 'ok',
            'redis_ok' => $redisOk,
            'redis_latency_ms' => $latencyMs,
            'queue_connection' => config('queue.default'),
            'worker_command' => 'php artisan queue:work redis --queue=documents,pdf,payroll,notifications,webhooks,default',
            'failed_jobs' => $failedJobs,
            'queues' => $queueStats,
        ];

        $this->outputResult($result);

        return self::SUCCESS;
    }

    private function outputResult(array $data): void
    {
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
}
