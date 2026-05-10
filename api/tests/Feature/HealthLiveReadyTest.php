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
            'checks' => ['database' => ['ok']],
            'timestamp',
        ]);
        $response->assertJson(['status' => 'ok']);
    }
}
