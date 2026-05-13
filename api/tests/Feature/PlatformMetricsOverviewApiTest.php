<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SuperAdmin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformMetricsOverviewApiTest extends TestCase
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

    public function test_super_admin_can_view_platform_metrics_overview(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 08:00:00', 'UTC'));

        try {
            DB::table('plans')->insert([
                ['id' => 1, 'name' => 'Starter', 'price_monthly' => 29, 'price_yearly' => 290, 'trial_days' => 14, 'is_active' => true],
                ['id' => 2, 'name' => 'Pro', 'price_monthly' => 99, 'price_yearly' => 990, 'trial_days' => 14, 'is_active' => true],
            ]);

            $active = Company::factory()->create(['plan_id' => 2, 'status' => 'active', 'currency' => 'DZD']);
            $trial = Company::factory()->create(['plan_id' => 1, 'status' => 'trial', 'currency' => 'DZD']);
            $suspended = Company::factory()->suspended()->create(['plan_id' => 1, 'currency' => 'EUR']);

            DB::table('subscriptions')->insert([
                ['company_id' => $active->id, 'plan' => 'pro', 'status' => 'active', 'payment_method' => 'manual', 'created_at' => now(), 'updated_at' => now()],
                ['company_id' => $trial->id, 'plan' => 'starter', 'status' => 'trial', 'payment_method' => 'manual', 'created_at' => now(), 'updated_at' => now()],
                ['company_id' => $suspended->id, 'plan' => 'starter', 'status' => 'past_due', 'payment_method' => 'manual', 'created_at' => now(), 'updated_at' => now()],
            ]);

            $invoiceId = DB::table('invoices')->insertGetId([
                'company_id' => $active->id,
                'number' => 'LEO-2026-0001',
                'amount' => 100,
                'currency' => 'DZD',
                'tax_amount' => 19,
                'total' => 119,
                'status' => 'paid',
                'due_date' => now()->addDays(5)->toDateString(),
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('invoices')->insert([
                'company_id' => $suspended->id,
                'number' => 'LEO-2026-0002',
                'amount' => 50,
                'currency' => 'DZD',
                'tax_amount' => 0,
                'total' => 50,
                'status' => 'overdue',
                'due_date' => now()->subDays(3)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('payments')->insert([
                'invoice_id' => $invoiceId,
                'company_id' => $active->id,
                'amount' => 119,
                'currency' => 'DZD',
                'method' => 'manual',
                'status' => 'completed',
                'paid_at' => now(),
                'created_at' => now(),
            ]);

            Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

            $response = $this->getJson('/api/v1/platform/metrics/overview');

            $response->assertOk();
            $response->assertJsonPath('data.revenue.currency', 'DZD');
            $response->assertJsonPath('data.revenue.mrr', 128);
            $response->assertJsonPath('data.revenue.arr', 1536);
            $response->assertJsonPath('data.revenue.collected_30d', 119);
            $response->assertJsonPath('data.revenue.overdue_total', 50);
            $response->assertJsonPath('data.companies.total', 3);
            $response->assertJsonPath('data.companies.active', 1);
            $response->assertJsonPath('data.companies.trial', 1);
            $response->assertJsonPath('data.companies.suspended', 1);
            $response->assertJsonPath('data.subscriptions.active', 1);
            $response->assertJsonPath('data.subscriptions.trial', 1);
            $response->assertJsonPath('data.subscriptions.past_due', 1);
            $response->assertJsonPath('data.billing.invoices_paid', 1);
            $response->assertJsonPath('data.billing.invoices_overdue', 1);
            $response->assertJsonPath('data.billing.payments_completed_30d', 1);
            $response->assertJsonStructure([
                'data' => [
                    'system' => ['php_version', 'laravel_version', 'memory_usage_mb', 'cache_driver', 'queue_driver', 'db_driver'],
                    'generated_at',
                ],
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_metrics_overview_requires_super_admin_authentication(): void
    {
        $response = $this->getJson('/api/v1/platform/metrics/overview');

        $response->assertUnauthorized();
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
