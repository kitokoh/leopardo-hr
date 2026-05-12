<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AgentControllerTest extends TestCase
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

    public function test_unauthenticated_user_cannot_run_agent(): void
    {
        $this->postJson('/api/v1/ai/agent/run')->assertStatus(401);
    }

    public function test_run_requires_task(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/ai/agent/run', []);
        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_list_workflows(): void
    {
        $this->getJson('/api/v1/ai/agent/workflows')->assertStatus(401);
    }

    public function test_authenticated_user_can_list_workflows(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/ai/agent/workflows');
        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }
}
