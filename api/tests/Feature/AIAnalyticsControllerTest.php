<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AIAnalyticsControllerTest extends TestCase
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

    public function test_authenticated_user_can_view_usage(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/ai/analytics/usage');
        $response->assertOk();
    }

    public function test_authenticated_user_can_view_costs(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/ai/analytics/costs');
        $response->assertOk();
    }

    public function test_authenticated_user_can_view_tools(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/ai/analytics/tools');
        $response->assertOk();
    }

    public function test_authenticated_user_can_view_errors(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/ai/analytics/errors');
        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_analytics(): void
    {
        $this->getJson('/api/v1/ai/analytics/usage')->assertStatus(401);
    }
}
