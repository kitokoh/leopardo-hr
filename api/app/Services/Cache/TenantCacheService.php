<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Tenant-scoped cache service with Upstash-compatible key patterns.
 *
 * Upstash Redis does not provide a portable tagged-cache contract across all
 * Laravel stores, so callers use explicit tenant keys and targeted invalidation.
 */
class TenantCacheService
{
    private const DEFAULT_TTL = 300;

    private const EMPLOYEE_LIST_TTL = 300;

    private const ATTENDANCE_REPORT_TTL = 900;

    private const MANAGER_DIGEST_TTL = 30;

    private const SCHEDULES_TTL = 300;

    public function remember(int|string $companyId, string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        return Cache::remember($this->tenantKey($companyId, $key), $ttl, $callback);
    }

    public function forget(int|string $companyId, string $key): bool
    {
        return Cache::forget($this->tenantKey($companyId, $key));
    }

    /**
     * Upstash-safe helper: callers should prefer exact keys because broad
     * pattern deletion is not portable across Redis providers.
     */
    public function forgetPattern(int|string $companyId, string $pattern): void
    {
        Cache::forget("tenant:{$companyId}:{$pattern}");
    }

    /**
     * @param  list<string>  $keys
     */
    public function forgetMany(int|string $companyId, array $keys): void
    {
        foreach ($keys as $key) {
            $this->forget($companyId, $key);
        }
    }

    public function flush(int|string $companyId): void
    {
        $store = Cache::store();

        if (method_exists($store, 'tags')) {
            $store->tags(["tenant:{$companyId}"])->flush();
        }
    }

    public function get(int|string $companyId, string $key): mixed
    {
        return Cache::get($this->tenantKey($companyId, $key));
    }

    public function put(int|string $companyId, string $key, mixed $value, int $ttl = self::DEFAULT_TTL): bool
    {
        return Cache::put($this->tenantKey($companyId, $key), $value, $ttl);
    }

    public function rememberEmployees(int|string $companyId, callable $callback): mixed
    {
        return $this->remember($companyId, 'employees:list', $callback, self::EMPLOYEE_LIST_TTL);
    }

    public function rememberManagerDigest(int|string $companyId, int|string $managerId, string $date, callable $callback): mixed
    {
        return $this->remember($companyId, "dashboard:manager-digest:{$managerId}:{$date}", $callback, self::MANAGER_DIGEST_TTL);
    }

    public function rememberSchedules(int|string $companyId, callable $callback): mixed
    {
        return $this->remember($companyId, 'schedules:list', $callback, self::SCHEDULES_TTL);
    }

    public function rememberAttendanceReport(int|string $companyId, string $period, callable $callback): mixed
    {
        return $this->remember($companyId, "attendance:report:{$period}", $callback, self::ATTENDANCE_REPORT_TTL);
    }

    public function invalidateEmployees(int|string $companyId): bool
    {
        return $this->forget($companyId, 'employees:list');
    }

    public function invalidateSchedules(int|string $companyId): bool
    {
        return $this->forget($companyId, 'schedules:list');
    }

    public function invalidateManagerDigest(int|string $companyId, int|string $managerId, string $date): bool
    {
        return $this->forget($companyId, "dashboard:manager-digest:{$managerId}:{$date}");
    }

    public function invalidateAttendanceReports(int|string $companyId, string $period): bool
    {
        return $this->forget($companyId, "attendance:report:{$period}");
    }

    private function tenantKey(int|string $companyId, string $key): string
    {
        return "tenant:{$companyId}:{$key}";
    }
}
