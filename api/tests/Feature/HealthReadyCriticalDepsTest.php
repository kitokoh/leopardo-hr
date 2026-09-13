<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

/**
 * #7255 — la readiness doit refleter les dependances CRITIQUES (base + Redis +
 * queue), pas seulement la base : un Redis HS restait invisible
 * (HTTP 200 + `status: ok`) alors que l'instance n'assurait plus ses jobs,
 * ses sessions ni ses e-mails (constat live du 2026-09-11).
 *
 * Le contrat de `/health` (probe Render + gate de deploiement, grep
 * `"status":"ok"`) est volontairement inchange : une dependance secondaire
 * degradee ne doit pas faire redemarrer une instance saine. C'est
 * `/health/ready` qui porte la verite de disponibilite — cf.
 * docs/ops/HEALTH_ENDPOINTS.md.
 */
class HealthReadyCriticalDepsTest extends TestCase
{
    public function test_ready_reports_redis_outage_as_failure(): void
    {
        config([
            // Redis « vraiment voulu » (cf. checkRedis) et queue synchrone :
            // le seul check en echec doit etre Redis.
            'database.redis.default.url' => 'redis://127.0.0.1:6379',
            'queue.default' => 'sync',
        ]);
        Redis::shouldReceive('connection')->andThrow(new RuntimeException('redis down'));

        $response = $this->getJson('/api/v1/health/ready');

        // Coeur du correctif : plus de faux vert.
        $response->assertStatus(503);
        $response->assertJsonPath('status', 'fail');
        $response->assertJsonPath('checks.redis.ok', false);
        $response->assertJsonPath('checks.database.ok', true);
        $response->assertJsonPath('failed_checks', ['redis']);
    }

    public function test_ready_reports_queue_outage_as_failure(): void
    {
        $this->app->instance('queue', new class
        {
            public function connection(): never
            {
                throw new RuntimeException('queue down');
            }
        });

        config([
            // Pas de Redis : seul le check queue doit echouer.
            'database.redis.default.url' => null,
            'cache.default' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'database',
        ]);

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(503);
        $response->assertJsonPath('status', 'fail');
        $response->assertJsonPath('checks.queue.ok', false);
        $response->assertJsonPath('checks.redis.status', 'skipped');
        $response->assertJsonPath('failed_checks', ['queue']);
    }

    public function test_ready_stays_ok_when_redis_is_not_configured(): void
    {
        // Dependance desactivee volontairement -> `skipped`, jamais un echec.
        config([
            'database.redis.default.url' => null,
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
        ]);

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('checks.redis.status', 'skipped');
        $response->assertJsonPath('failed_checks', []);
    }

    public function test_health_endpoint_keeps_its_render_probe_contract(): void
    {
        // Decision explicite #7255 : `/health` reste pilote par la base pour ne
        // pas transformer une panne Redis partielle en redemarrage d'instance
        // (Render healthCheckPath + 6 workflows parsent ce payload).
        config([
            'database.redis.default.url' => 'redis://127.0.0.1:6379',
            'queue.default' => 'sync',
        ]);
        Redis::shouldReceive('connection')->andThrow(new RuntimeException('redis down'));

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.redis.ok', false);
    }
}
