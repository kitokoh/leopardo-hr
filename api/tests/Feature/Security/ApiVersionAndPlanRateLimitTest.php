<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
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
        // Issue #4683 / #4456 (ADR-0014) : codes plans canoniques
        // Free/Pilot/Operations/Enterprise — « Starter » est un legacy
        // migré vers Pilot par PlanSeeder, et resolvePlanLimit() ne lit
        // que pilot_per_minute (jamais starter_per_minute).
        config(['security.plan_rate_limits.pilot_per_minute' => 2]);

        DB::table('plans')->updateOrInsert(['name' => 'Pilot'], [
            'price_monthly' => 29,
            'price_yearly' => 290,
            'max_employees' => 20,
            'features' => '{}',
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $planId = DB::table('plans')->where('name', 'Pilot')->value('id');

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

