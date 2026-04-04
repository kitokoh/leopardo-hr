<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
    }
}
