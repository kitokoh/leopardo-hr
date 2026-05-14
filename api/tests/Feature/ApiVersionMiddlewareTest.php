<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ApiVersionMiddlewareTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_api_responses_include_version_header(): void
    {
        $employee = Employee::factory()->create([
            'role' => 'manager',
            'status' => 'active',
        ]);
        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertHeader('X-API-Version', 'v1');
    }
}
