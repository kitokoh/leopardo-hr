<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\FeaturePlanMatrix;
use App\Models\Subscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class FeatureFlagControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->ensureFeaturePlanMatrixTable();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_authenticated_user_can_view_feature_matrix(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        FeaturePlanMatrix::create([
            'feature_key' => 'vehicles',
            'plan' => 'business',
            'enabled' => true,
        ]);
        FeaturePlanMatrix::create([
            'feature_key' => 'vehicles',
            'plan' => 'starter',
            'enabled' => false,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/feature-flags/matrix');

        $response->assertOk();
        $response->assertJsonPath('data.vehicles.business', true);
        $response->assertJsonPath('data.vehicles.starter', false);
    }

    public function test_check_uses_active_subscription_plan_and_limit(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
        FeaturePlanMatrix::create([
            'feature_key' => 'vehicles',
            'plan' => 'business',
            'enabled' => true,
            'limit_value' => 25,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/feature-flags/check/vehicles');

        $response->assertOk();
        $response->assertJsonPath('data.feature', 'vehicles');
        $response->assertJsonPath('data.plan', 'business');
        $response->assertJsonPath('data.enabled', true);
        $response->assertJsonPath('data.limit', 25);
    }

    public function test_check_falls_back_to_trial_without_active_subscription(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        FeaturePlanMatrix::create([
            'feature_key' => 'ai',
            'plan' => 'trial',
            'enabled' => false,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/feature-flags/check/ai');

        $response->assertOk();
        $response->assertJsonPath('data.plan', 'trial');
        $response->assertJsonPath('data.enabled', false);
    }

    public function test_unknown_feature_is_disabled(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'enterprise',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/feature-flags/check/unknown_feature');

        $response->assertOk();
        $response->assertJsonPath('data.plan', 'enterprise');
        $response->assertJsonPath('data.enabled', false);
        $response->assertJsonPath('data.limit', null);
    }

    public function test_tenant_user_cannot_update_platform_feature_matrix(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->putJson('/api/v1/feature-flags/matrix', [
            'feature_key' => 'vehicles',
            'plan' => 'business',
            'enabled' => true,
            'limit_value' => 25,
        ])->assertForbidden();

        $this->assertSame(0, FeaturePlanMatrix::count());
    }

    private function ensureFeaturePlanMatrixTable(): void
    {
        if (Schema::hasTable('feature_plan_matrix')) {
            return;
        }

        Schema::create('feature_plan_matrix', function (Blueprint $table): void {
            $table->id();
            $table->string('feature_key', 50);
            $table->string('plan', 30);
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('limit_value')->nullable();
            $table->timestamps();
            $table->unique(['feature_key', 'plan']);
        });
    }
}
