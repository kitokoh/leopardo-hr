<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

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
    private array $defaultQueues = ['default', 'pdf', 'notifications', 'payroll', 'webhooks'];

    public function handle(): int
    {
        $queues = $this->option('queue') ?: $this->defaultQueues;

        // Measure Redis round-trip latency
        $start = microtime(true);
        $ping  = null;

        try {
            $ping = Redis::connection('default')->ping();
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            $redisOk   = true;
        } catch (\Throwable $e) {
            $latencyMs = null;
            $redisOk   = false;
            $this->outputResult([
                'status'            => 'error',
                'redis_ok'          => false,
                'redis_latency_ms'  => null,
                'error'             => $e->getMessage(),
                'queues'            => [],
            ]);
            return self::FAILURE;
        }

        // Collect queue depths via LLEN (Redis list length for each queue)
        $queueStats = [];
        $prefix = config('database.redis.options.prefix', '');

        foreach ($queues as $queue) {
            try {
                // Laravel stores queues as `queues:{name}` in Redis
                $length = Redis::connection('default')->llen("{$prefix}queues:{$queue}");
                $queueStats[$queue] = [
                    'depth'  => (int) $length,
                    'status' => 'ok',
                ];
            } catch (\Throwable $e) {
                $queueStats[$queue] = [
                    'depth'  => null,
                    'status' => 'error',
                    'error'  => $e->getMessage(),
                ];
            }
        }

        $result = [
            'status'           => 'ok',
            'redis_ok'         => $redisOk,
            'redis_latency_ms' => $latencyMs,
            'queues'           => $queueStats,
        ];

        $this->outputResult($result);

        return self::SUCCESS;
    }

    private function outputResult(array $data): void
    {
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
