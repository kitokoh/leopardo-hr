<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthLiveReadyTest extends TestCase
{
    public function test_live_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response->assertOk();
        $response->assertJsonStructure(['status', 'timestamp']);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_ready_returns_ok_when_db_up(): void
    {
        $response = $this->getJson('/api/v1/health/ready');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'checks' => [
                'database' => ['ok'],
                // #7255 — la readiness couvre désormais les dépendances
                // critiques, pas seulement la base.
                'redis' => ['ok'],
                'queue' => ['ok'],
            ],
            'failed',
            'timestamp',
        ]);
        $response->assertJson(['status' => 'ok', 'failed' => []]);
    }

    public function test_ready_exposes_critical_dependencies_not_just_database(): void
    {
        // Garde anti-régression #7255 : Redis et la queue étaient absents de la
        // readiness, si bien qu'une panne Redis/queue restait invisible pour un
        // monitor externe (le statut ne dépendait que de la base).
        $checks = $this->getJson('/api/v1/health/ready')->json('checks');

        self::assertIsArray($checks);
        self::assertArrayHasKey('database', $checks);
        self::assertArrayHasKey('redis', $checks);
        self::assertArrayHasKey('queue', $checks);
    }
}
