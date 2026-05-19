<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Cache\TenantCacheService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TenantCacheServiceTest extends TestCase
{
    private TenantCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TenantCacheService;
    }

    public function test_remember_caches_value_with_tenant_key(): void
    {
        $result = $this->service->remember(1, 'employees.count', fn () => 42);

        $this->assertSame(42, $result);
        $this->assertSame(42, Cache::get('tenant:1:employees.count'));
    }

    public function test_put_and_get_round_trip(): void
    {
        $this->service->put(5, 'config.theme', 'dark');
        $value = $this->service->get(5, 'config.theme');

        $this->assertSame('dark', $value);
    }

    public function test_forget_removes_cached_value(): void
    {
        $this->service->put(3, 'temp.key', 'value');
        $this->service->forget(3, 'temp.key');

        $this->assertNull($this->service->get(3, 'temp.key'));
    }

    public function test_different_tenants_have_isolated_keys(): void
    {
        $this->service->put(1, 'setting', 'tenant1');
        $this->service->put(2, 'setting', 'tenant2');

        $this->assertSame('tenant1', $this->service->get(1, 'setting'));
        $this->assertSame('tenant2', $this->service->get(2, 'setting'));
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->service->get(99, 'nonexistent'));
    }
}
