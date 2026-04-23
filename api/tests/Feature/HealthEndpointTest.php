<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_returns_ok_with_checks_matrix(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'version',
            'checks' => [
                'database' => ['ok'],
                'redis',
                'storage' => ['ok'],
            ],
            'timestamp',
        ]);

        $response->assertJson([
            'status' => 'ok',
            'checks' => [
                'database' => ['ok' => true],
                'storage' => ['ok' => true],
            ],
        ]);
    }
}
