<?php

namespace Tests\Feature;

use Tests\TestCase;

class RequestIdMiddlewareTest extends TestCase
{
    public function test_response_contains_request_id_header(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertHeader('X-Request-Id');
    }

    public function test_provided_request_id_is_echoed_back(): void
    {
        $customId = 'test-request-id-12345';

        $response = $this->getJson('/api/v1/health', [
            'X-Request-Id' => $customId,
        ]);

        $response->assertOk();
        $response->assertHeader('X-Request-Id', $customId);
    }
}
