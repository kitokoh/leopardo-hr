<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformCompanySubscriptionApiTest extends TestCase
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

    public function test_super_admin_can_view_and_update_company_subscription(): void
    {
        DB::table('plans')->insert([
            ['id' => 1, 'name' => 'Starter', 'price_monthly' => 29, 'price_yearly' => 290, 'max_employees' => 10, 'trial_days' => 14, 'is_active' => true],
            ['id' => 2, 'name' => 'Business', 'price_monthly' => 149, 'price_yearly' => 1490, 'max_employees' => 100, 'trial_days' => 14, 'is_active' => true],
        ]);

        $company = Company::factory()->create([
            'plan_id' => 1,
            'status' => 'trial',
            'subscription_start' => '2026-05-01',
            'subscription_end' => '2026-05-15',
            'currency' => 'DZD',
        ]);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->getJson("/api/v1/platform/companies/{$company->id}/subscription")
            ->assertOk()
            ->assertJsonPath('data.status', 'trial')
            ->assertJsonPath('data.plan.name', 'Starter')
            ->assertJsonPath('data.plan.max_employees', 10);

        $response = $this->patchJson("/api/v1/platform/companies/{$company->id}/subscription", [
            'plan_id' => 2,
            'status' => 'active',
            'subscription_start' => '2026-05-08',
            'subscription_end' => '2027-05-08',
            'notes' => 'Upgrade Business apres adoption pointage.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan.name', 'Business')
            ->assertJsonPath('data.plan.price_monthly', 149)
            ->assertJsonPath('data.subscription_end', '2027-05-08')
            ->assertJsonPath('data.notes', 'Upgrade Business apres adoption pointage.');

        $company->refresh();
        $this->assertSame(2, $company->plan_id);
        $this->assertSame('active', $company->status);
        $this->assertSame('Upgrade Business apres adoption pointage.', $company->notes);
    }

    public function test_subscription_update_rejects_invalid_status_and_dates(): void
    {
        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Starter',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $company = Company::factory()->create(['plan_id' => 1]);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/companies/{$company->id}/subscription", [
            'plan_id' => 1,
            'status' => 'vip_free_forever',
            'subscription_start' => '2026-06-01',
            'subscription_end' => '2026-05-01',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'subscription_end']);
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
