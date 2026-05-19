<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

class TenantCacheService
{
    private const DEFAULT_TTL = 300; // 5 minutes

    public function remember(int $companyId, string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $cacheKey = $this->tenantKey($companyId, $key);

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    public function forget(int $companyId, string $key): bool
    {
        return Cache::forget($this->tenantKey($companyId, $key));
    }

    public function forgetPattern(int $companyId, string $pattern): void
    {
        $prefix = "tenant:{$companyId}:{$pattern}";
        Cache::forget($prefix);
    }

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

    private function tenantKey(int $companyId, string $key): string
    {
        return "tenant:{$companyId}:{$key}";
    }
}
