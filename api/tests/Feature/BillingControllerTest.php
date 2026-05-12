<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Subscription;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class BillingControllerTest extends TestCase
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

    public function test_manager_can_view_subscription(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => 'active',
            'payment_method' => 'stripe',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/billing/subscription');
        $response->assertOk();
        $response->assertJsonPath('data.plan', 'business');
    }

    public function test_manager_can_upgrade_subscription(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/billing/subscription/upgrade', [
            'plan' => 'enterprise',
            'payment_method' => 'stripe',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.plan', 'enterprise');
    }

    public function test_employee_cannot_upgrade_subscription(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/billing/subscription/upgrade', [
            'plan' => 'enterprise',
        ])->assertStatus(403);
    }

    public function test_manager_can_cancel_subscription(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/billing/subscription/cancel', [
            'reason' => 'Too expensive',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
    }

    public function test_manager_can_list_invoices(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Invoice::create([
            'company_id' => $company->id,
            'number' => 'LEO-2026-0001',
            'amount' => 99.00,
            'currency' => 'EUR',
            'total' => 99.00,
            'status' => 'paid',
            'due_date' => now()->addDays(30),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/billing/invoices');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_invalid_plan_returns_validation_error(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/billing/subscription/upgrade', [
            'plan' => 'invalid_plan',
        ])->assertStatus(422);
    }
}
