<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Issue #1774 — Le rate limiting ne doit jamais répondre 500 quand le
 * stockage du compteur échoue (épisode prod 2026-08-13 : cache Redis/Upstash
 * saturé → « Server Error » sur quasi toutes les routes throttlées).
 *
 * Le correctif vit dans `App\Http\Middleware\ResilientThrottleRequests`
 * (substitué à l'alias `throttle` dans bootstrap/app.php) : échec du stockage
 * → `report()` + **429 dégradé** avec Retry-After au lieu d'un 500 ; chemin
 * nominal inchangé.
 */
class RateLimiterResilienceTest extends TestCase
{
    /**
     * Store de cache qui lève systématiquement — simule un Redis/Upstash
     * injoignable ou saturé.
     */
    private function throwingStore(): Store
    {
        return new class implements Store
        {
            public function get($key): mixed
            {
                throw new \RuntimeException('Simulated cache outage');
            }

            /**
             * @param  array<string>  $keys
             * @return array<string, mixed>
             */
            public function many(array $keys): array
            {
                throw new \RuntimeException('Simulated cache outage');
            }

            public function put($key, $value, $seconds): bool
            {
                throw new \RuntimeException('Simulated cache outage');
            }

            /**
             * @param  array<string, mixed>  $values
             */
            public function putMany(array $values, $seconds): bool
            {
                throw new \RuntimeException('Simulated cache outage');
            }

            public function increment($key, $value = 1): int|bool
            {
                throw new \RuntimeException('Simulated cache outage');
            }

            public function decrement($key, $value = 1): int|bool
            {
                throw new \RuntimeException('Simulated cache outage');
            }

            public function forever($key, $value): bool
            {
                throw new \RuntimeException('Simulated cache outage');
            }

            public function forget($key): bool
            {
                throw new \RuntimeException('Simulated cache outage');
            }

            public function flush(): bool
            {
                throw new \RuntimeException('Simulated cache outage');
            }

            public function getPrefix(): string
            {
                return '';
            }
        };
    }

    public function test_throttled_route_returns_degraded_429_when_counter_storage_fails(): void
    {
        // Le RateLimiter singleton partage le Repository du cache par défaut :
        // on remplace son store par un store en panne (équivalent prod).
        /** @var Repository $repository */
        $repository = Cache::store();
        $originalStore = $repository->getStore();
        $repository->setStore($this->throwingStore());

        try {
            $response = $this->getJson('/api/v1/i18n/catalog/fr');

            // Avant le correctif : 500 « Server Error ».
            $response->assertStatus(429);
            $response->assertHeader('Retry-After');
        } finally {
            $repository->setStore($originalStore);
        }
    }

    public function test_health_route_still_returns_200_during_counter_storage_failure(): void
    {
        /** @var Repository $repository */
        $repository = Cache::store();
        $originalStore = $repository->getStore();
        $repository->setStore($this->throwingStore());

        try {
            // /health est exclu du rate limiting (Limit::none) : il ne doit pas
            // être affecté par la panne du compteur.
            $this->getJson('/api/v1/health')->assertOk();
        } finally {
            $repository->setStore($originalStore);
        }
    }

    public function test_rate_limiter_still_returns_429_when_limit_exceeded(): void
    {
        // Comportement nominal : dépassement du limiter 'auth-sensitive'
        // (10/min par email+IP) → 429 + Retry-After, sans impact du correctif.
        $route = '/api/v1/i18n/catalog/fr';

        for ($i = 0; $i < 10; $i++) {
            $this->getJson($route)->assertOk();
        }

        $this->getJson($route)->assertStatus(429)->assertHeader('Retry-After');
    }
}
