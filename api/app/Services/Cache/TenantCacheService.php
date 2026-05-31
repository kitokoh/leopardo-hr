<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Tenant-scoped cache service with Upstash-compatible TTL patterns.
 *
 * Upstash Redis supports standard GET/SET/TTL — no SCAN, no tagged cache.
 * All keys are prefixed with `tenant:{id}:` for easy manual invalidation.
 */
class TenantCacheService
{
    // TTL constants (seconds)
    private const DEFAULT_TTL = 300;          // 5 minutes
    private const EMPLOYEE_LIST_TTL = 300;    // 5 minutes — Plan 63
    private const ATTENDANCE_REPORT_TTL = 900; // 15 minutes — Plan 63

    public function remember(int $companyId, string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $cacheKey = $this->tenantKey($companyId, $key);

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    public function forget(int $companyId, string $key): bool
    {
        return Cache::forget($this->tenantKey($companyId, $key));
    }

    /**
     * Forget all keys matching a pattern for a tenant.
     * Note: Upstash does not support SCAN/pattern deletion.
     * Callers should pass exact keys or use forgetMany() with a list.
     */
    public function forgetPattern(int $companyId, string $pattern): void
    {
        $prefix = "tenant:{$companyId}:{$pattern}";
        Cache::forget($prefix);
    }

    /**
     * Invalidate a list of known keys for a tenant.
     */
    public function forgetMany(int $companyId, array $keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($this->tenantKey($companyId, $key));
        }
    }

    /**
     * Flush all cached data for a tenant.
     * With Upstash (non-tagged driver), this only clears known keys via tags if available.
     */
    public function flush(int $companyId): void
    {
        $tags = ["tenant:{$companyId}"];

        if (method_exists(Cache::store(), 'tags')) {
            Cache::tags($tags)->flush();
        }
    }

    public function get(int $companyId, string $key): mixed
    {
        return Cache::get($this->tenantKey($companyId, $key));
    }

    public function put(int $companyId, string $key, mixed $value, int $ttl = self::DEFAULT_TTL): bool
    {
        return Cache::put($this->tenantKey($companyId, $key), $value, $ttl);
    }

    // ── Plan 63 — Upstash-optimised helpers ─────────────────────────────────

    /**
     * Cache employee list for a tenant (TTL: 5 min).
     */
    public function rememberEmployees(int $companyId, callable $callback): mixed
    {
        return $this->remember($companyId, 'employees:list', $callback, self::EMPLOYEE_LIST_TTL);
    }

    /**
     * Cache attendance report for a tenant+period (TTL: 15 min).
     */
    public function rememberAttendanceReport(int $companyId, string $period, callable $callback): mixed
    {
        return $this->remember($companyId, "attendance:report:{$period}", $callback, self::ATTENDANCE_REPORT_TTL);
    }

    /**
     * Invalidate employee list cache when employees change.
     */
    public function invalidateEmployees(int $companyId): bool
    {
        return $this->forget($companyId, 'employees:list');
    }

    /**
     * Invalidate attendance reports for a tenant.
     */
    public function invalidateAttendanceReports(int $companyId, string $period): bool
    {
        return $this->forget($companyId, "attendance:report:{$period}");
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    private function tenantKey(int $companyId, string $key): string
    {
        return "tenant:{$companyId}:{$key}";
    }
}
