<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ApiVersionMiddlewareTest extends TestCase
{
    public function test_api_responses_include_version_header(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertHeader('X-API-Version', 'v1');
    }
}
