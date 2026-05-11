<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnitEnum;

/**
 * Endpoint /api/v1/health : sonde "live + ready" consommee par :
 *
 * - le deploy hook Render (`DEFAULT_API_HEALTHCHECK_URL` cherche `"status":"ok"`)
 * - les futurs scrapers de supervision (UptimeRobot, Better Uptime, etc.)
 *
 * La reponse inclut toujours `status`, `version` et la matrice `checks`.
 * Le HTTP code est 200 tant qu'au moins la base de donnees repond ; si la DB
 * tombe on renvoie 503 pour que Render detecte immediatement une instance
 * non disponible.
 *
 * Les checks secondaires (Redis, storage) sont `degraded` en cas d'echec
 * mais ne declenchent pas un 503 : l'API reste partiellement servable
 * (les jobs/queues peuvent etre degrades, pas l'auth).
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $version = $this->stringConfigValue(config('app.version'));

        $database = $this->checkDatabase();
        $redis = $this->checkRedis();
        $storage = $this->checkStorage();
        $queue = $this->checkQueue();
        $memory = $this->checkMemory();

        $globalOk = $database['ok'];

        $payload = [
            'status' => $globalOk ? 'ok' : 'fail',
            'version' => $version,
            'environment' => app()->environment(),
            'checks' => [
                'database' => $database,
                'redis' => $redis,
                'storage' => $storage,
                'queue' => $queue,
                'memory' => $memory,
            ],
            'uptime_seconds' => defined('LARAVEL_START')
                ? (int) round(microtime(true) - LARAVEL_START)
                : null,
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json($payload, $globalOk ? 200 : 503);
    }

    /**
     * @return array{ok: bool, latency_ms?: int, error?: string}
     */
    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');

            return [
                'ok' => true,
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => class_basename($e),
            ];
        }
    }

    /**
     * @return array{ok: bool, status?: string, latency_ms?: int, error?: string}
     */
    private function checkRedis(): array
    {
        // Redis est optionnel (cache/sessions/queues). On considere Redis
        // "vraiment voulu" si :
        //   (a) REDIS_URL est explicitement defini -> `config('database.redis.default.url')`
        //       n'a PAS de default, il vaut `null` tant que REDIS_URL n'est pas pose ;
        //   (b) ou un driver applicatif (cache/queue/session) utilise redis.
        //
        // On NE regarde PAS `database.redis.default.host` : son default `127.0.0.1`
        // est indistinguable d'une config explicite, et on veut justement eviter de
        // bloquer quelques secondes sur un `tcp connect` vers un Redis inexistant
        // sur chaque requete `/health`. `env()` est volontairement evite : apres
        // `php artisan config:cache` (prod), `env()` renvoie null.
        $urlConfigured = ! empty(config('database.redis.default.url'));
        $driverUsesRedis = config('cache.default') === 'redis'
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis';

        if (! $urlConfigured && ! $driverUsesRedis) {
            return ['ok' => true, 'status' => 'skipped'];
        }

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
                'status' => 'degraded',
                'error' => class_basename($e),
            ];
        }
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk($this->filesystemDiskName());
            // Laravel 11 : les disques sont en `throw => false` par defaut,
            // donc `put()` retourne `false` au lieu de lever. On verifie
            // explicitement la valeur de retour pour vraiment detecter un
            // disque non inscriptible.
            $written = $disk->put('.healthcheck', (string) now()->timestamp);
            $disk->delete('.healthcheck');

            return ['ok' => (bool) $written];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => class_basename($e),
            ];
        }
    }

    private function filesystemDiskName(): string|UnitEnum
    {
        $disk = config('filesystems.default', 'local');

        return is_string($disk) || $disk instanceof UnitEnum ? $disk : 'local';
    }

    /**
     * GET /api/v1/health/live — 200 if the process is running.
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/health/ready — 200 if DB is up.
     */
    public function ready(): JsonResponse
    {
        $database = $this->checkDatabase();

        $status = $database['ok'] ? 'ok' : 'fail';
        $code = $database['ok'] ? 200 : 503;

        return response()->json([
            'status' => $status,
            'checks' => ['database' => $database],
            'timestamp' => now()->toIso8601String(),
        ], $code);
    }

    /**
     * @return array{ok: bool, driver?: string, size?: int}
     */
    private function checkQueue(): array
    {
        $driver = (string) config('queue.default', 'sync');

        if ($driver === 'sync') {
            return ['ok' => true, 'driver' => 'sync'];
        }

        try {
            $size = app('queue')->connection()->size();

            return [
                'ok' => true,
                'driver' => $driver,
                'size' => $size,
            ];
        } catch (Throwable) {
            return ['ok' => false, 'driver' => $driver];
        }
    }

    /**
     * @return array{ok: bool, usage_mb: int, peak_mb: int, limit_mb: int|null}
     */
    private function checkMemory(): array
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit') ?: '-1');

        return [
            'ok' => $limit < 0 || $usage < $limit * 0.9,
            'usage_mb' => (int) round($usage / 1048576),
            'peak_mb' => (int) round($peak / 1048576),
            'limit_mb' => $limit > 0 ? (int) round($limit / 1048576) : null,
        ];
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '-1') {
            return -1;
        }

        $value = (int) $limit;
        $unit = strtolower(substr($limit, -1));

        return match ($unit) {
            'g' => $value * 1073741824,
            'm' => $value * 1048576,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function stringConfigValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
