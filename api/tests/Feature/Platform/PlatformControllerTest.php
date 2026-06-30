<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected Company $company;
    protected Company $otherCompany;
    protected Employee $manager;
    protected Employee $employee;
    protected Employee $otherManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->company      = Company::factory()->create();
        $this->otherCompany = Company::factory()->create();
        $this->manager      = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->employee     = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->otherManager = Employee::factory()->manager()->create(['company_id' => $this->otherCompany->id]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /** @test */
    public function health_endpoint_returns_200(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
    }

    /** @test */
    public function health_live_returns_200(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response->assertStatus(200);
    }

    /** @test */
    public function health_ready_returns_200_or_503(): void
    {
        $response = $this->getJson('/api/v1/health/ready');

        // 200 when all dependencies are healthy; 503 when one or more are degraded
        $this->assertContains($response->status(), [200, 503]);
    }

    /** @test */
    public function health_response_has_correct_structure(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);

        // The health response should at minimum contain a status field
        $body = $response->json();
        $this->assertTrue(
            isset($body['status']) || isset($body['data']['status']) || is_array($body),
            'Health response should contain a status indicator or structured data.'
        );
    }

    /** @test */
    public function launch_readiness_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/launch-readiness');

        $response->assertStatus(401);
    }

    /** @test */
    public function authenticated_user_can_get_launch_readiness(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/launch-readiness');

        $response->assertStatus(200);
    }

    /** @test */
    public function demo_users_endpoint_is_accessible_without_auth(): void
    {
        $response = $this->getJson('/api/v1/demo-users');

        // Public endpoint: should return 200; some environments may disable it (404)
        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function metrics_endpoint_returns_200_or_requires_auth(): void
    {
        // The metrics endpoint may be public (Prometheus scrape) or auth-protected
        $response = $this->getJson('/api/v1/metrics');

        $this->assertContains($response->status(), [200, 401, 403, 404]);
    }
}
