<?php

namespace Tests\Feature;

use App\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformPlanApiTest extends TestCase
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

    public function test_super_admin_can_list_platform_plans_for_subscription_forms(): void
    {
        DB::table('plans')->insert([
            [
                'id' => 1,
                'name' => 'Business',
                'price_monthly' => 149,
                'price_yearly' => 1490,
                'max_employees' => 100,
                'features' => json_encode(['rh' => true, 'finance' => true]),
                'trial_days' => 14,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'name' => 'Starter',
                'price_monthly' => 29,
                'price_yearly' => 290,
                'max_employees' => 10,
                'features' => json_encode(['rh' => true]),
                'trial_days' => 14,
                'is_active' => true,
            ],
        ]);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $response = $this->getJson('/api/v1/platform/plans');

        $response->assertOk();
        $response->assertJsonPath('data.items.0.name', 'Starter');
        $response->assertJsonPath('data.items.0.price_monthly', 29);
        $response->assertJsonPath('data.items.0.max_employees', 10);
        $response->assertJsonPath('data.items.0.features.rh', true);
        $response->assertJsonPath('data.items.1.name', 'Business');
        $response->assertJsonPath('data.items.1.features.finance', true);
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password123'),
        ]);
    }
}
