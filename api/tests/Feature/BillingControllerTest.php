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

    public function test_manager_can_renew_cancelled_subscription(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => 'cancelled',
            'cancelled_at' => now()->subDay(),
            'cancel_reason' => 'Pause client',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/billing/subscription/renew');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.cancel_reason', null);
    }

    public function test_employee_cannot_cancel_or_renew_subscription(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/billing/subscription/cancel')->assertStatus(403);
        $this->postJson('/api/v1/billing/subscription/renew')->assertStatus(403);
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

    public function test_invoice_list_is_scoped_to_authenticated_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
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
        Invoice::create([
            'company_id' => $otherCompany->id,
            'number' => 'LEO-2026-0002',
            'amount' => 299.00,
            'currency' => 'EUR',
            'total' => 299.00,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/billing/invoices');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.number', 'LEO-2026-0001');
    }

    public function test_manager_can_show_own_invoice_and_cannot_show_other_tenant_invoice(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'number' => 'LEO-2026-0001',
            'amount' => 99.00,
            'currency' => 'EUR',
            'total' => 99.00,
            'status' => 'paid',
            'due_date' => now()->addDays(30),
        ]);
        $otherInvoice = Invoice::create([
            'company_id' => $otherCompany->id,
            'number' => 'LEO-2026-0002',
            'amount' => 299.00,
            'currency' => 'EUR',
            'total' => 299.00,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/billing/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.number', 'LEO-2026-0001');
        $this->getJson("/api/v1/billing/invoices/{$otherInvoice->id}")
            ->assertNotFound();
    }

    public function test_manager_can_download_invoice_pdf_for_own_company_only(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'number' => 'LEO-2026-0001',
            'amount' => 99.00,
            'currency' => 'EUR',
            'total' => 99.00,
            'status' => 'paid',
            'due_date' => now()->addDays(30),
        ]);
        $otherInvoice = Invoice::create([
            'company_id' => $otherCompany->id,
            'number' => 'LEO-2026-0002',
            'amount' => 299.00,
            'currency' => 'EUR',
            'total' => 299.00,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ]);

        Sanctum::actingAs($manager);

        $pdf = $this->get("/api/v1/billing/invoices/{$invoice->id}/pdf");

        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->get("/api/v1/billing/invoices/{$otherInvoice->id}/pdf")
            ->assertNotFound();
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
