<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Payroll\Domain\Models\Payment;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Isolation tenant & RBAC billing — DEP-BC21 (issue #6252).
 *
 * Couvre :
 *   1. cross-tenant : company B ne peut ni lire ni modifier la souscription
 *      ou les factures de company A (404 sûr) ;
 *   2. RBAC : employé simple → 403 sur `/billing/*` ;
 *   3. support super-admin : `/platform/companies/{company}/subscription`
 *      utilisable par le super-admin, routes tenant inaccessibles sans tenant ;
 *   4. commande console : `billing:reconcile-payments` sans contexte tenant
 *      traite toutes les companies sans erreur, sans fuite en surface API.
 */
class BillingIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function tenantWithBillingData(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Subscription $subscription */
        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => 'active',
            'payment_method' => 'stripe',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        /** @var Invoice $invoice */
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-'.substr((string) $company->id, 0, 8),
            'amount' => '100.00',
            'currency' => 'USD',
            'tax_amount' => '0.00',
            'total' => '100.00',
            'status' => 'sent',
            'due_date' => now()->addDays(15),
        ]);

        return [$company, $subscription, $invoice];
    }

    public function test_company_b_cannot_read_company_a_invoice(): void
    {
        [$companyA, , $invoiceA] = $this->tenantWithBillingData();
        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        Subscription::create([
            'company_id' => $companyB->id,
            'plan' => 'business',
            'status' => 'active',
            'payment_method' => 'stripe',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($managerB);

        // Facture du tenant A inaccessible depuis le tenant B (404 sûr).
        $this->getJson('/api/v1/billing/invoices/'.$invoiceA->id)->assertStatus(404);
        $this->getJson('/api/v1/billing/invoices/'.$invoiceA->id.'/pdf')->assertStatus(404);

        // La souscription vue par B n'est pas celle de A.
        $this->getJson('/api/v1/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.company_id', $companyB->id);
    }

    public function test_company_b_cannot_act_on_company_a_subscription(): void
    {
        [$companyA] = $this->tenantWithBillingData();
        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        Subscription::create([
            'company_id' => $companyB->id,
            'plan' => 'business',
            'status' => 'active',
            'payment_method' => 'stripe',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($managerB);

        // L'upgrade/cancel agit sur la souscription du TENANT CONNECTÉ (B),
        // jamais sur celle de A — aucun id de ressource A n'est accepté.
        $this->postJson('/api/v1/billing/subscription/cancel', [])
            ->assertOk();

        // A est intacte.
        $this->assertSame(
            'active',
            Subscription::where('company_id', $companyA->id)->firstOrFail()->status
        );
    }

    public function test_plain_employee_cannot_manage_billing(): void
    {
        $company = Company::factory()->create();
        assert($company instanceof Company);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/billing/subscription/upgrade', [
            'plan' => 'enterprise',
        ])->assertStatus(403);

        $this->postJson('/api/v1/billing/subscription/cancel', [])->assertStatus(403);
    }

    public function test_super_admin_can_access_platform_subscription_route(): void
    {
        [$companyA] = $this->tenantWithBillingData();

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

        $this->actingAs($superAdmin, 'super_admin_api')
            ->getJson('/api/v1/platform/companies/'.$companyA->id.'/subscription')
            ->assertOk();
    }

    public function test_tenant_manager_cannot_reach_platform_routes(): void
    {
        [$companyA] = $this->tenantWithBillingData();
        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);

        Sanctum::actingAs($managerA);

        // La surface platform (support) n'est pas accessible avec un token tenant.
        $this->getJson('/api/v1/platform/companies/'.$companyA->id.'/subscription')
            ->assertStatus(401);
    }

    public function test_console_reconciliation_treats_all_companies_without_api_leak(): void
    {
        [$companyA, , $invoiceA] = $this->tenantWithBillingData();
        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        Subscription::create([
            'company_id' => $companyB->id,
            'plan' => 'business',
            'status' => 'active',
            'payment_method' => 'stripe',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
        /** @var Subscription $subscriptionB */
        $subscriptionB = Subscription::create([
            'company_id' => $companyB->id,
            'plan' => 'business',
            'status' => 'active',
            'payment_method' => 'stripe',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
        /** @var Invoice $invoiceB */
        $invoiceB = Invoice::create([
            'company_id' => $companyB->id,
            'subscription_id' => $subscriptionB->id,
            'number' => 'INV-B-001',
            'amount' => '50.00',
            'currency' => 'USD',
            'tax_amount' => '0.00',
            'total' => '50.00',
            'status' => 'sent',
            'due_date' => now()->addDays(15),
        ]);

        Payment::create([
            'invoice_id' => $invoiceA->id,
            'company_id' => $companyA->id,
            'amount' => '100.00',
            'currency' => 'USD',
            'method' => 'card',
            'provider_reference' => 'pi_a_1234567890',
            'status' => 'completed',
            'paid_at' => now(),
            'created_at' => now(),
        ]);
        Payment::create([
            'invoice_id' => $invoiceB->id,
            'company_id' => $companyB->id,
            'amount' => '50.00',
            'currency' => 'USD',
            'method' => 'card',
            'provider_reference' => 'pi_b_1234567890',
            'status' => 'completed',
            'paid_at' => now(),
            'created_at' => now(),
        ]);

        // Console, sans contexte tenant : traite A ET B (exit 0, aucune fuite).
        $this->artisan('billing:reconcile-payments --apply')->assertExitCode(0);

        $this->assertSame('paid', $invoiceA->fresh()->status);
        $this->assertSame('paid', $invoiceB->fresh()->status);

        // Aucune route API tenant ne permet de lire l'état de l'autre tenant.
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);
        Sanctum::actingAs($managerB);
        $this->getJson('/api/v1/billing/invoices/'.$invoiceA->id)->assertStatus(404);
    }
}
