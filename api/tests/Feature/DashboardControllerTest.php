<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
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

    public function test_authenticated_user_can_view_summary(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/dashboard/summary');
        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_unauthenticated_user_cannot_view_summary(): void
    {
        $this->getJson('/api/v1/dashboard/summary')->assertStatus(401);
    }

    public function test_authenticated_user_can_view_recent_activity(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/dashboard/recent-activity');
        $response->assertOk();
    }

    public function test_authenticated_user_can_view_kpi(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/dashboard/kpi');
        $response->assertOk();
    }

    public function test_kpi_accepts_month_parameter(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/dashboard/kpi?month=2026-04');
        $response->assertOk();
    }
}
