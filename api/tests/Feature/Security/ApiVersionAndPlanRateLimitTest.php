<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ApiVersionAndPlanRateLimitTest extends TestCase
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

    public function test_api_responses_include_version_headers(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response->assertOk();
        $response->assertHeader('X-API-Version', 'v1');
        $response->assertHeader('X-API-Supported-Versions', 'v1');
    }

    public function test_unsupported_requested_api_version_is_rejected(): void
    {
        $response = $this->withHeader('X-API-Version', 'v2')->getJson('/api/v1/health/live');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'UNSUPPORTED_API_VERSION',
                'requested_version' => 'v2',
                'supported_versions' => ['v1'],
            ]);
    }

    public function test_authenticated_api_is_rate_limited_by_current_plan(): void
    {
        config(['security.plan_rate_limits.starter_per_minute' => 2]);

        DB::table('plans')->updateOrInsert(['name' => 'Starter'], [
            'price_monthly' => 29,
            'price_yearly' => 290,
            'max_employees' => 20,
            'features' => '{}',
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $planId = DB::table('plans')->where('name', 'Starter')->value('id');

        $company = Company::factory()->create(['plan_id' => $planId]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'plan-rate-limit@example.test',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertStatus(429);
    }
}
