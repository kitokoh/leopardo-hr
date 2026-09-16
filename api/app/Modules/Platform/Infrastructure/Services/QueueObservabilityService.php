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
    /**
     * Named queues also used by QueueHealthCheck / HealthController.
     *
     * #7540 — publique : `/health` (HealthController) et l'observabilité
     * doivent lire **la même** liste. Deux listes entretenues en parallèle
     * recréeraient exactement le défaut corrigé ici (deux définitions pour le
     * même mot).
     */
    public const QUEUES = ['default', 'documents', 'pdf', 'payroll', 'notifications', 'webhooks'];

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

        // #7540 — mêmes totaux pour la ventilation : `queue_total_depth` reste
        // la taille brute (contrat inchangé), les deux autres disent ce qui est
        // réellement *à traiter* par opposition à ce qui est *planifié*.
        $totalPending = array_sum(array_filter(
            array_column($queues, 'pending'),
            static fn (mixed $value): bool => is_int($value),
        ));
        $totalScheduled = array_sum(array_filter(
            array_column($queues, 'scheduled'),
            static fn (mixed $value): bool => is_int($value),
        ));

        $staleTasks = array_values(array_filter(
            $scheduledTasks,
            static fn (array $task): bool => $task['is_stale'] === true,
        ));

        return [
            'redis' => $redis,
            'queue_connection' => (string) config('queue.default'),
            'queues' => $queues,
            'queue_total_depth' => $totalDepth,
            'queue_total_pending' => $totalPending,
            'queue_total_scheduled' => $totalScheduled,
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
            // Issue #1768 : Predis retourne un objet Status (__toString 'PONG').
            $ok = $response === true
                || in_array(strtoupper((string) $response), ['PONG', '+PONG'], true);

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
     * #7540 — ventilation d'une queue : *éligible maintenant* vs *planifiée*.
     *
     * `DatabaseQueue::size()` (que `/health` et `queueDepths()` consomment)
     * compte **toutes** les lignes de la queue sans regarder `available_at` :
     * un job planifié à +7 jours y pèse autant qu'un job bloqué depuis 7 jours.
     * C'est ce qui a fait ouvrir #7540 (« la file `notifications` ne se draine
     * pas ») alors que les 23 jobs en cause étaient le **planning du drip
     * d'essai** (`SendTrialDripEmailJob`, dispatches différés +1/+3/+7 jours
     * depuis `VerifyTrialSignup`), et que le worker drainait correctement tout
     * le reste.
     *
     * `QueueHealthCheck::databaseQueueDepths()` employait déjà la bonne
     * définition (« éligible maintenant »). Ce helper en fait la définition
     * **partagée**, sans toucher à `size` : le contrat historique des
     * consommateurs (garde de dérive, tableaux de bord) reste inchangé, et le
     * signal devient capable de distinguer un arriéré d'un agenda.
     *
     * @param  list<string>  $queues
     * @return array<string, array{pending: int, scheduled: int, reserved: int}>|null
     *         `null` quand la ventilation n'est pas mesurable (driver non
     *         `database`, ou table `jobs` illisible) — on ne devine pas.
     */
    public static function queueBreakdown(array $queues): ?array
    {
        if ((string) config('queue.default', 'sync') !== 'database') {
            return null;
        }

        $table = (string) config('queue.connections.database.table', 'jobs');
        $now = (int) now()->timestamp;

        /** @var array<string, int> $pending */
        $pending = [];
        /** @var array<string, int> $scheduled */
        $scheduled = [];
        /** @var array<string, int> $reserved */
        $reserved = [];

        try {
            $pending = self::groupedJobCount($table, $queues, 'available_at', '<=', $now, true);
            $scheduled = self::groupedJobCount($table, $queues, 'available_at', '>', $now, true);
            $reserved = self::groupedJobCount($table, $queues, 'reserved_at', '>', 0, false);
        } catch (Throwable) {
            return null;
        }

        $breakdown = [];

        foreach ($queues as $queue) {
            $breakdown[$queue] = [
                'pending' => $pending[$queue] ?? 0,
                'scheduled' => $scheduled[$queue] ?? 0,
                'reserved' => $reserved[$queue] ?? 0,
            ];
        }

        return $breakdown;
    }

    /**
     * Un `COUNT(*) GROUP BY queue` sur la table `jobs`, filtré par une borne.
     *
     * @param  list<string>  $queues
     * @return array<string, int>
     */
    private static function groupedJobCount(
        string $table,
        array $queues,
        string $column,
        string $operator,
        int $value,
        bool $requireNotReserved,
    ): array {
        $query = DB::table($table)
            ->select('queue', DB::raw('COUNT(*) AS cnt'))
            ->whereIn('queue', $queues)
            ->groupBy('queue');

        if ($requireNotReserved) {
            $query->whereNull('reserved_at');
        }

        if ($column === 'reserved_at') {
            $query->whereNotNull('reserved_at');
        } else {
            $query->where($column, $operator, $value);
        }

        $rows = $query->pluck('cnt', 'queue');

        $counts = [];

        foreach ($queues as $queue) {
            $counts[$queue] = (int) ($rows[$queue] ?? 0);
        }

        return $counts;
    }

    /**
     * @return array<int, array{name: string, depth: int, ok: bool, pending: int|null, scheduled: int|null, reserved: int|null}>
     */
    private function queueDepths(): array
    {
        $driver = (string) config('queue.default', 'sync');
        $breakdown = self::queueBreakdown(self::QUEUES);

        if ($driver === 'sync') {
            return array_map(
                static fn (string $name): array => [
                    'name' => $name,
                    'depth' => 0,
                    'ok' => true,
                    'pending' => 0,
                    'scheduled' => 0,
                    'reserved' => 0,
                ],
                self::QUEUES,
            );
        }

        $queues = [];

        foreach (self::QUEUES as $queue) {
            try {
                $depth = (int) app('queue')->connection()->size($queue);
                $queues[] = [
                    'name' => $queue,
                    'depth' => $depth,
                    'ok' => true,
                    'pending' => $breakdown[$queue]['pending'] ?? null,
                    'scheduled' => $breakdown[$queue]['scheduled'] ?? null,
                    'reserved' => $breakdown[$queue]['reserved'] ?? null,
                ];
            } catch (Throwable) {
                $queues[] = [
                    'name' => $queue,
                    'depth' => 0,
                    'ok' => false,
                    'pending' => null,
                    'scheduled' => null,
                    'reserved' => null,
                ];
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
